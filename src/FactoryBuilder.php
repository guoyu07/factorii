<?php

namespace ddinchev\factorii;

use Closure;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;

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
     * @param bool $runValidation
     * @return \yii\db\ActiveRecord
     */
    public function create(array $attributes = [], $runValidation = false)
    {
        $instance = $this->make($attributes);
        $instance->save($runValidation);
        return $instance;
    }

    /**
     * Create a collection of models and persist them to the database.
     * @param int $count
     * @param array $attributes
     * @param bool $runValidation
     * @return \yii\db\ActiveRecord[]
     */
    public function createMultiple($count, array $attributes = [], $runValidation = false)
    {
        $results = $this->makeMultiple($count, $attributes);
        foreach ($results as $result) {
            $result->save($runValidation);
        }
        return $results;
    }

    /**
     * Make an instance of the model with the given attributes.
     * @param array $attributes
     * @return \yii\db\ActiveRecord
     * @throws \yii\base\InvalidParamException
     */
    public function make(array $attributes = [])
    {
        if (!isset($this->definitions[$this->class][$this->name])) {
            throw new InvalidArgumentException("Unable to locate factory with name [{$this->name}] [{$this->class}].");
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
    public function makeMultiple($count, array $attributes = [])
    {
        return array_map(
            function () use ($attributes) {
                return $this->make($attributes);
            }, range(1, $count)
        );
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
}
