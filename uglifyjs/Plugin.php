<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Tool\Plugin\Uglifyjs;

use \Zicht\Tool\Plugin as BasePlugin;
use \Zicht\Tool\Container\Container;
use \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use \Symfony\Component\Yaml\Yaml;

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
        $config = Yaml::parse($container->resolve(array('uglifyjs', 'config')));

        $container->method(
            array('uglifyjs', 'cmd'),
            function(Container $container, $root) use($config) {
                $localExec = 'node_modules/.bin/uglifyjs';
                $exec = file_exists($localExec) ? 'node ' . $localExec : 'uglifyjs';

                $root = ltrim(str_replace(getcwd(), '', $root), '/');

                $commands = array();
                $targetDir = ltrim(rtrim($root, '/') . '/' . $config['web_root'] . '/' . $config['target_dir'], '/');
                $commands[]= 'mkdir -p ' . escapeshellarg($targetDir);
                foreach ($config['resources'] as $targetFile => $resource) {
                    $commands[] = 'echo ' . escapeshellarg('Uglifyjs ' . $targetDir . '/' . $targetFile);
                    $commands[] = sprintf(
                        '%s %s -o %s %s',
                        $exec,
                        $container->resolve('VERBOSE') ? '-v --stats' : '',
                        escapeshellarg($targetDir . '/' . $targetFile),
                        "\\\n    " . join("\\\n    ",
                            array_map(
                                'escapeshellarg',
                                array_map(
                                    function($file) use($config, $root) {
                                        return ltrim(
                                            rtrim($root, '/')
                                                . '/' . $config['web_root']
                                                . '/' . $config['src_dir']
                                                . '/' . $file,
                                            '/'
                                        );
                                    },
                                    $resource['files']
                                )
                            )
                        )
                    );
                }
                return join(";\\\n", $commands);
            }
        );
    }
}