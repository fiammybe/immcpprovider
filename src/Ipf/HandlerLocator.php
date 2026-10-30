<?php

declare(strict_types=1);

namespace ImMcpProvider\Ipf;

use icms;
use icms_ipf_Handler;
use Mcp\Exception\ToolCallException;
use Throwable;

final class HandlerLocator
{
    public function locate(string $moduleName, string $handlerName): icms_ipf_Handler
    {
        $handler = icms_getModuleHandler($handlerName, $moduleName, null, true);

        if (! $handler instanceof icms_ipf_Handler) {
            $handler = $this->instantiateLegacyHandler($moduleName, $handlerName);
        }

        $this->loadObjectClass($handler, $moduleName, $handlerName);

        return $handler;
    }

    private function instantiateLegacyHandler(string $moduleName, string $handlerName): icms_ipf_Handler
    {
        $this->includeFirstExisting($this->handlerFiles($moduleName, $handlerName));

        foreach ($this->handlerClasses($moduleName, $handlerName) as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, icms_ipf_Handler::class)) {
                throw new ToolCallException(
                    "Class '{$class}' for handler '{$handlerName}' in module '{$moduleName}' does not extend icms_ipf_Handler."
                );
            }

            return $this->instantiate($class, $moduleName, $handlerName);
        }

        throw new ToolCallException($this->explainMissingHandler($moduleName, $handlerName));
    }

    private function instantiate(string $class, string $moduleName, string $handlerName): icms_ipf_Handler
    {
        try {
            return new $class(icms::$xoopsDB);
        } catch (Throwable $exception) {
            error_log("mcpapi: cannot construct {$class}: {$exception}");

            $type = $exception::class;

            throw new ToolCallException(
                "Handler '{$handlerName}' in module '{$moduleName}' could not be constructed ({$type})."
            );
        }
    }

    private function loadObjectClass(icms_ipf_Handler $handler, string $moduleName, string $handlerName): void
    {
        $className = (string) $handler->className;

        if ($className === '' || class_exists($className)) {
            return;
        }

        $this->includeFirstExisting($this->objectFiles($moduleName, $handlerName));

        if (! class_exists($className)) {
            throw new ToolCallException(
                "Object class '{$className}' of handler '{$handlerName}' in module '{$moduleName}' could not be loaded."
            );
        }
    }

    /** @return array<int, string> */
    private function handlerFiles(string $moduleName, string $handlerName): array
    {
        $directory = $this->classDirectory($moduleName);

        return $this->uniqueFiles([
            "{$directory}/" . ucfirst(strtolower($handlerName)) . 'Handler.php',
            "{$directory}/" . ucfirst($handlerName) . 'Handler.php',
            "{$directory}/{$handlerName}Handler.php",
            "{$directory}/{$handlerName}.php",
        ]);
    }

    /** @return array<int, string> */
    private function objectFiles(string $moduleName, string $handlerName): array
    {
        $directory = $this->classDirectory($moduleName);

        return $this->uniqueFiles([
            "{$directory}/" . ucfirst($handlerName) . '.php',
            "{$directory}/" . ucfirst(strtolower($handlerName)) . '.php',
            "{$directory}/{$handlerName}.php",
        ]);
    }

    /** @return array<int, string> */
    private function handlerClasses(string $moduleName, string $handlerName): array
    {
        $item = ucfirst($handlerName);

        return array_values(array_unique([
            "mod_{$moduleName}_{$item}Handler",
            ucfirst(strtolower($moduleName)) . "{$item}Handler",
            ucfirst($moduleName) . "{$item}Handler",
            "{$item}Handler",
        ]));
    }

    private function explainMissingHandler(string $moduleName, string $handlerName): string
    {
        $files = array_map(
            fn (string $file): string => basename($file) . (is_file($file) ? ' (found)' : ' (missing)'),
            $this->handlerFiles($moduleName, $handlerName)
        );

        return sprintf(
            "Handler '%s' in module '%s' could not be loaded. Looked for classes [%s] after checking files [%s].",
            $handlerName,
            $moduleName,
            implode(', ', $this->handlerClasses($moduleName, $handlerName)),
            implode(', ', $files)
        );
    }

    /** @param array<int, string> $files */
    private function includeFirstExisting(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                include_once $file;
            }
        }
    }

    /**
     * @param array<int, string> $files
     * @return array<int, string>
     */
    private function uniqueFiles(array $files): array
    {
        return array_values(array_unique($files));
    }

    private function classDirectory(string $moduleName): string
    {
        return ICMS_ROOT_PATH . "/modules/{$moduleName}/class";
    }
}
