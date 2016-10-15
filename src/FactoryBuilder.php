<?php

namespace ddinchev\factorii;

use Closure;
use yii\base\InvalidParamException;

class FactoryBuilder
{
    /**
     * The model definitions in the container.
     * @var array
     */
    protected $definitions;

    /**
     * The model being built.
     * @var string
     */
    protected $class;

    /**
     * The name of the model being built.
     * @var string
     */
    protected $name = 'default';

    /**
     * The Faker instance for the builder.
     * @var \Faker\Generator
     */
    private $_faker;

    /**
     * Create an new builder instance.
     * @param string $class
     * @param string $name
     * @param array $definitions
     * @param \Faker\Generator $faker
     */
    public function __construct($class, $name, array $definitions, \Faker\Generator $faker)
    {
        $this->name = $name;
        $this->class = $class;
        $this->definitions = $definitions;
        $this->_faker = $faker;
    }

    /**
     * Create single model and persist in to the database.
     * @param array $attributes
     * @return \yii\db\ActiveRecord
     */
    public function create(array $attributes = [])
    {
        $instance = $this->build($attributes);
        $instance->insert(false);
        return $instance;
    }

    /**
     * Create a collection of models and persist them to the database.
     * @param int $count
     * @param array $attributes
     * @return \yii\db\ActiveRecord[]
     */
    public function createList($count, array $attributes = [])
    {
        return $this->listOf($count, function () use ($attributes) {
            return $this->create($attributes);
        });
    }

    /**
     * Make an instance of the model with the given attributes.
     * @param array $attributes
     * @return \yii\db\ActiveRecord
     * @throws \yii\base\InvalidParamException
     */
    public function build(array $attributes = [])
    {
        if (!isset($this->definitions[$this->class][$this->name])) {
            throw new InvalidParamException("Unable to locate factory with name [{$this->name}] [{$this->class}].");
        }

        $factoryDefinitionCallable = $this->definitions[$this->class][$this->name];
        $definition = call_user_func($factoryDefinitionCallable, $this->_faker, $attributes);
        $evaluated = $this->callClosureAttributes(array_merge($definition, $attributes));

        /** @var \yii\db\ActiveRecord $instance */
        $instance = new $this->class;
        $instance->setAttributes($evaluated, false);
        return $instance;
    }

    /**
     * Make a collection of models with the given attributes.
     * @param int $count
     * @param array $attributes
     * @return \yii\db\ActiveRecord[]
     */
    public function buildList($count, array $attributes = [])
    {
        return $this->listOf($count, function () use ($attributes) {
            return $this->build($attributes);
        });
    }

    /**
     * Evaluate any Closure attributes on the attribute array.
     * @param array $attributes
     * @return array
     */
    protected function callClosureAttributes(array $attributes)
    {
        foreach ($attributes as &$attribute) {
            $attribute = $attribute instanceof Closure ? $attribute($attributes) : $attribute;
        }
        return $attributes;
    }

    /**
     * Create array of $count elements, generated by calling the supplied $generator that many times.
     * @param $count
     * @param callable $generator
     * @return array
     */
    protected function listOf($count, Callable $generator)
    {
        return array_map($generator, range(1, $count));
    }
}
