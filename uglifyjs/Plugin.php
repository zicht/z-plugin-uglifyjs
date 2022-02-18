<?php
/**
 * @copyright Zicht Online <https://zicht.nl>
 */

namespace Zicht\Tool\Plugin\Uglifyjs;

use Zicht\Tool\Plugin as BasePlugin;
use Zicht\Tool\Container\Container;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Yaml\Yaml;

class Plugin extends BasePlugin
{
    protected $config = null;

    public function appendConfiguration(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('uglifyjs')
                    ->children()
                        ->scalarNode('config')->isRequired()->end()
                    ->end()
                ->end()
            ->end();
    }

    public function setContainer(Container $container)
    {
        $config = Yaml::parse(file_get_contents($container->resolve(array('uglifyjs', 'config'))));

        $container->method(
            array('uglifyjs', 'cmd'),
            function(Container $container, $root) use ($config) {
                $root = ltrim(str_replace(getcwd(), '', $root), '/');

                $localExec = 'node_modules/.bin/uglifyjs';
                $exec = file_exists(rtrim($root, '/') . '/' . $localExec) ? 'node ' . $localExec : 'uglifyjs';

                $commands = array();
                $targetDir = ltrim($config['web_root'] . '/' . $config['target_dir'], '/');
                $commands[] = ($root ? 'cd ' . escapeshellarg($root) . ' && ' : '')
                    . 'mkdir -p ' . escapeshellarg($targetDir);

                foreach ($config['resources'] as $targetFile => $resource) {
                    $commands[] = 'echo ' . escapeshellarg('Uglifyjs ' . $targetDir . '/' . $targetFile);
                    $commands[] = sprintf(
                        '%s%s -o %s %s',
                        $exec,
                        $container->resolve('VERBOSE') ? ' -v --stats' : '',
                        escapeshellarg($targetDir . '/' . $targetFile),
                        " \\\n    " . join(" \\\n    ",
                            array_map(
                                'escapeshellarg',
                                array_map(
                                    function($file) use($config) {
                                        return ltrim($config['web_root'] . '/' . ltrim($config['src_dir'] . '/' . $file, '/'), '/');
                                    },
                                    $resource['files']
                                )
                            )
                        )
                    );
                }

                return join("; \\\n", $commands);
            }
        );
    }
}