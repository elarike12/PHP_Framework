<?php

/**
 * Class for managing modules.
 *
 * Copyright @ Elar Must.
 */

namespace Framework\Module;

use Throwable;
use ReflectionClass;
use Psr\Log\LogLevel;
use RuntimeException;
use Framework\Framework;

class ModuleRegistry {
    private array $modules = [];
    private array $loadedModules = [];
    public const MODULE_PATHS = ['Modules', 'vendor'];

    public function __construct(private Framework $framework) {

        $modulesFound = [];
        $graph = [];
        // Load other module configurations into array
        foreach ($this::MODULE_PATHS as $path) {
            // Ignore files.
            if (!is_dir(BASE_PATH . '/' . $path)) {
                continue;
            }

            $vendors = array_diff(scandir(BASE_PATH . '/' . $path), ['..', '.']);
            foreach ($vendors as $vendor) {
                // Ignore files.
                if (!is_dir(BASE_PATH . '/' . $path . '/' . $vendor)) {
                    continue;
                }

                $modules = array_diff(scandir(BASE_PATH . '/' . $path . '/' . $vendor), ['..', '.']);
                foreach ($modules as $module) {
                    $modulePath = BASE_PATH . '/' . $path . '/' . $vendor . '/' . $module;
                    $moduleName = $vendor . '\\' . $module;
                    $moduleClass = ucfirst($vendor) . '\\' . ucfirst($module) . '\\' . ucfirst($module);

                    if (!class_exists($moduleClass) || !in_array(ModuleInterface::class, class_implements($moduleClass))) {
                        continue;
                    }

                    if (isset($modulesFound[$moduleName])) {
                        throw new RuntimeException('Ambiguous module ' . $moduleName . ' in ' . $modulePath . '! ' . $moduleName . ' already exists in ' . $modulesFound[$moduleName][0] . '.');
                    }

                    try {
                        $reflection = new ReflectionClass($moduleClass);
                        $definedClassName = $reflection->getName();
                        // Create new module instance
                        $newModule = $this->framework->getClassContainer()->get($definedClassName, [$this->framework, $moduleName, $modulePath]);

                        $modulesFound[$newModule->getName()] = $newModule;
                        $graph[$newModule->getName()] = [$newModule->loadBefore(), $newModule->loadAfter()];
                    } catch (Throwable $e) {
                        $this->framework->getLogger()->log(LogLevel::ERROR, $e->getMessage(), identifier: 'framework');
                        $this->framework->getLogger()->log(LogLevel::ERROR, $e->getTraceAsString(), identifier: 'framework');
                    }
                }
            }
        }

        foreach ($this->topologicalSort($graph) as $module) {
            $this->modules[$module] = $modulesFound[$module];
        }
    }

    public function loadModule(Framework $framework, ModuleInterface $module): ModuleInterface {
        if (method_exists($module::class, 'onLoad')) {
            $module->onLoad(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onLoad'));
        }

        if ($framework->isTaskWorker()){
            if (method_exists($module::class, 'onTaskWorkerStart')) {
                $module->onTaskWorkerStart(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onTaskWorkerStart'));
            }
        } else {
            if (method_exists($module::class, 'onWorkerStart')) {
                $module->onWorkerStart(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onWorkerStart'));
            }
        }

        $this->loadedModules[$module->getName()] = $module;
        return $module;
    }

    public function unloadModule(Framework $framework, ModuleInterface $module): ModuleInterface {
        if (method_exists($module::class, 'onUnload')) {
            $module->onUnload(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onUnload'));
        }

        if ($framework->isTaskWorker()){
            if (method_exists($module::class, 'onTaskWorkerStop')) {
                $module->onTaskWorkerStop(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onTaskWorkerStop'));
            }
        } else {
            if (method_exists($module::class, 'onWorkerStop')) {
                $module->onWorkerStop(...$this->framework->getClassContainer()->prepareFunctionArguments($module::class, 'onWorkerStop'));
            }
        }

        unset($this->loadedModules[$module->getName()]);
        return $module;
    }

    public function getModules(): array {
        return $this->loadedModules;
    }

    public function getAllModules() {
        return $this->modules;
    }

    public function getModule(string $moduleName): ?ModuleInterface {
        return $this->modules[$moduleName] ?? null;
    }

    /**
     * Perform a topological sort on a directed acyclic graph.
     *
     * This function takes a directed acyclic graph as input, represented as an associative array.
     * Each vertex in the graph is a key in the array, and its associated value is an array with two sub-arrays:
     * - The first sub-array contains a list of previous vertexes (vertexes that precede the current vertex).
     * - The second sub-array contains a list of following vertex (vertexes that come after the current vertex).
     *
     * @param array $graph The directed acyclic graph to perform the topological sort on.
     *
     * @return array An array containing the topological ordering of vertexes.
     */
    public function topologicalSort(array $graph): array {
        foreach ($graph as $vertex => $edgeTypes) {
            foreach ($edgeTypes[0] ?? [] as $beforeVertex) {
                if (!isset($graph[$beforeVertex])) {
                    $this->framework->getLogger()->log(LogLevel::WARNING, 'Could not locate dependency \'' . $beforeVertex . '\' for \''  . $vertex . '\'!', identifier: 'framework');
                    continue;
                }

                if (!in_array($vertex, $graph[$beforeVertex][1] ?? [])) {
                    $graph[$beforeVertex][1][] = $vertex;
                }
            }
        }

        foreach ($graph as $vertex => $edgeTypes) {
            $graph[$vertex] = $edgeTypes[1] ?? [];
        }

        $inDegree = [];
        $sorted = [];

        // Initialize in-degree for each vertex
        foreach ($graph as $vertex => $adjList) {
            $inDegree[$vertex] = 0;
        }

        // Calculate in-degree for each vertex
        foreach ($graph as $vertex => $adjList) {
            foreach ($adjList as $adjVertex) {
                if (array_key_exists($adjVertex, $inDegree)) {
                    $inDegree[$adjVertex]++;
                } else {
                    unset($graph[$vertex][array_search($adjVertex, $graph[$vertex])]);
                }
            }
        }

        // Initialize queue with vertices that have no incoming edges
        $queue = array_filter(array_keys($inDegree), function ($vertex) use ($inDegree) {
            return $inDegree[$vertex] == 0;
        });

        // Perform topological sort
        while (!empty($queue)) {
            $vertex = array_shift($queue);
            $sorted[] = $vertex;

            foreach ($graph[$vertex] as $adjVertex) {
                $inDegree[$adjVertex]--;
                if ($inDegree[$adjVertex] == 0) {
                    $queue[] = $adjVertex;
                }
            }
        }

        // Check for circular dependency
        if (count($sorted) != count($graph)) {
            foreach ($graph as $vertex => $edges) {
                if (!in_array($vertex, $sorted)) {
                    $this->framework->getLogger()->log(LogLevel::WARNING, $vertex . ' has circular dependency!', identifier: 'framework');
                }
            }
        }

        return $sorted;
    }
}
