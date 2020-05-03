<?php

declare(strict_types=1);

namespace Lit\Air;

use Lit\Air\Psr\Container;
use Lit\Air\Psr\ContainerException;
use Lit\Air\Recipe\AbstractRecipe;
use Lit\Air\Recipe\AutowireRecipe;
use Lit\Air\Recipe\BuilderRecipe;
use Lit\Air\Recipe\Decorator\AbstractRecipeDecorator;
use Lit\Air\Recipe\Decorator\CallbackDecorator;
use Lit\Air\Recipe\Decorator\SingletonDecorator;
use Lit\Air\Recipe\FixedValueRecipe;
use Lit\Air\Recipe\InstanceRecipe;
use Lit\Air\Recipe\RecipeInterface;

/**
 * Configurator helps to build an array configuration, and writes array configuration into a container.
 * http://litphp.github.io/docs/air-config
 */
class Configurator
{
    protected static $decorators = [
        'callback' => CallbackDecorator::class,
        'singleton' => SingletonDecorator::class,
    ];

    /**
     * Write a configuration array into a container
     *
     * @param Container $container The container.
     * @param array     $config    The configuration array.
     * @param boolean   $force     Whether overwrite existing values.
     * @return void
     */
    public static function config(Container $container, array $config, bool $force = true): void
    {
        foreach ($config as $key => $value) {
            if (!$force && $container->has($key)) {
                continue;
            }
            self::write($container, $key, $value);
        }
    }

    /**
     * Convert a mixed value into a recipe.
     *
     * @param mixed $value The value.
     * @return RecipeInterface
     */
    public static function convertToRecipe($value): RecipeInterface
    {
        if (is_object($value) && $value instanceof RecipeInterface) {
            return $value;
        }

        if (is_callable($value)) {
            return (new BuilderRecipe($value))->singleton();
        }

        if (is_array($value)) {
            $r = self::convertArrayToRecipe($value);
            if ($r !== null) {
                return $r;
            }
            trigger_error("array should be wrapped with C::value", E_USER_NOTICE);
        }

        return AbstractRecipe::value($value);
    }

    /**
     * Configuration indicating a singleton
     *
     * @param string $classname The class name.
     * @param array  $extra     Extra parameters.
     * @return array
     */
    public static function singleton(string $classname, array $extra = []): array
    {
        return self::decorateSingleton(self::instance($classname, $extra));
    }

    /**
     * Decorate a configuration, makes it a singleton (\Lit\Air\Recipe\Decorator\SingletonDecorator)
     *
     * @param array $config The configuration.
     * @return array
     */
    public static function decorateSingleton(array $config): array
    {
        $config['decorator'] = $config['decorator'] ?? [];
        $config['decorator']['singleton'] = true;

        return $config;
    }

    /**
     * Decorate a configuration with provided callback
     *
     * @param array    $config   The configuration.
     * @param callable $callback The callback.
     * @return array
     */
    public static function decorateCallback(array $config, callable $callback): array
    {
        $config['decorator'] = $config['decorator'] ?? [];
        $config['decorator']['callback'] = $callback;

        return $config;
    }

    /**
     * Configuration indicating an autowired entry.
     *
     * @param string $classname The class name.
     * @param array  $extra     Extra parameters.
     * @param bool   $cached    Whether to save the instance if it's not defined in container.
     * @return array
     */
    public static function produce(string $classname, array $extra = [], bool $cached = true): array
    {
        return [
            '$' => 'autowire',
            $classname,
            $extra,
            $cached,
        ];
    }

    /**
     * Configuration indicating an instance created by factory.
     *
     * @param string $classname The class name.
     * @param array  $extra     Extra parameters.
     * @return array
     */
    public static function instance(string $classname, array $extra = []): array
    {
        return [
            '$' => 'instance',
            $classname,
            $extra,
        ];
    }

    /**
     * Configuration indicating an alias
     *
     * @param string ...$key Multiple keys will be auto joined.
     * @return array
     */
    public static function alias(string ...$key): array
    {
        return [
            '$' => 'alias',
            self::join(...$key),
        ];
    }

    /**
     * Configuration wrapping a builder method
     *
     * @param callable $builder The builder method.
     * @param array    $extra   Extra parameters.
     * @return array
     */
    public static function builder(callable $builder, array $extra = []): array
    {
        return [
            '$' => 'builder',
            $builder,
            $extra,
        ];
    }

    /**
     * Configuration wraps an arbitary value. For arrays it's recommended to always wrap with this.
     *
     * @param mixed $value The value.
     * @return array
     */
    public static function value($value): array
    {
        return [
            '$' => 'value',
            $value,
        ];
    }

    protected static function write(Container $container, $key, $value)
    {
        if (is_scalar($value) || is_resource($value)) {
            $container->set($key, $value);
            return;
        }

        if (
            substr($key, -2) === '::'
            && class_exists(substr($key, 0, -2))
        ) {
            $container->set($key, self::mapArrayValueToRecipe($value));
            return;
        }

        $recipe = self::convertToRecipe($value);

        if ($recipe instanceof FixedValueRecipe) {
            $container->set($key, $recipe->getValue());
        } else {
            $container->flush($key);
            $container->define($key, $recipe);
        }
    }

    protected static function mapArrayValueToRecipe(array $arr): array
    {
        $result = [];
        foreach ($arr as $k => $v) {
            $result[$k] = self::convertToRecipe($v);
            if ($result[$k] instanceof FixedValueRecipe) {
                $result[$k] = $result[$k]->getValue();
            }
        }

        return $result;
    }

    protected static function convertArrayToRecipe(array $arr): ?RecipeInterface
    {
        if (array_key_exists(0, $arr) && !empty($arr['$'])) {
            return self::makeRecipe($arr);
        }

        if (Utils::isSequentialArray($arr, 1) && is_string($arr[0])) {
            return new AutowireRecipe($arr[0], [], false);
        }

        if (Utils::isSequentialArray($arr, 2) && is_string($arr[0]) && class_exists($arr[0])) {
            return new InstanceRecipe($arr[0], $arr[1]);
        }

        return null;
    }

    protected static function makeRecipe(array $arr): RecipeInterface
    {
        $type = $arr['$'];
        unset($arr['$']);

        if (
            array_key_exists($type, [
            'alias' => 1,
            'autowire' => 1,
            'instance' => 1,
            'builder' => 1,
            'value' => 1,
            ])
        ) {
            $valueDecorator = $arr['decorator'] ?? null;
            unset($arr['decorator']);

            $builder = [AbstractRecipe::class, $type];
            assert(is_callable($builder));
            /**
             * @var RecipeInterface $recipe
             */
            $recipe = $builder(...$arr);

            if ($valueDecorator) {
                $recipe = self::wrapRecipeWithDecorators($valueDecorator, $recipe);
            }

            return $recipe;
        }

        throw new ContainerException("cannot understand given recipe");
    }

    /**
     * Apply decorators to a recipe and return the decorated recipe
     *
     * @param array           $decorators Assoc array of decorator names => options.
     * @param RecipeInterface $recipe     The decorated recipe instance.
     * @return RecipeInterface
     */
    protected static function wrapRecipeWithDecorators(array $decorators, RecipeInterface $recipe): RecipeInterface
    {
        foreach ($decorators as $name => $option) {
            if (isset(self::$decorators[$name])) {
                $decorateFn = [self::$decorators[$name], 'decorate'];
                assert(is_callable($decorateFn));
                $recipe = call_user_func($decorateFn, $recipe);
            } elseif (is_subclass_of($name, AbstractRecipeDecorator::class)) {
                $decorateFn = [$name, 'decorate'];
                assert(is_callable($decorateFn));
                $recipe = call_user_func($decorateFn, $recipe);
            } else {
                throw new ContainerException("cannot understand recipe decorator [$name]");
            }

            assert($recipe instanceof AbstractRecipeDecorator);
            if (!empty($option)) {
                $recipe->setOption($option);
            }
        }

        return $recipe;
    }

    /**
     * Join multiple strings with air conventional separator `::`
     *
     * @param string ...$args Parts of the key to be joined.
     * @return string
     */
    public static function join(string ...$args): string
    {
        return implode('::', $args);
    }
}
