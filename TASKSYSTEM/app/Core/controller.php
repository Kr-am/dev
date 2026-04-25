<?php

class Controller
{
    protected function model(string $model)
    {
        $path = "../app/Models/{$model}.php";

        if (!file_exists($path)) {
            throw new Exception("Model {$model} not found");
        }

        require_once $path;
        return new $model();
    }

    /**
     * Load a view
     */
   protected function view(string $view, array $data = [])
    {

         // Load config

        $viewFile = __DIR__ . "/../Views/{$view}.php";

        if (!file_exists($viewFile)) {
            throw new Exception("View {$view} not found");
        }

        extract($data);

        $contentView = $viewFile;

        require __DIR__ . "/../Views/layout/app.php";
    }

    /**
     * Redirect helper
     */
    protected function redirect(string $path)
    {
         // Load config
        $config = require __DIR__ . '/../Config/config.php';

        // If $path is already full URL, leave it; otherwise prepend app_url
        if (!str_starts_with($path, 'http')) {
            $path = rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
        }

        header("Location: {$path}");
        exit;
    }

}