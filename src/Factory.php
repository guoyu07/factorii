<?php

namespace ddinchev\factorii;

use ArrayAccess;
use yii\base\Component;
use Yii;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;

class Factory extends Component implements ArrayAccess
{
    /**
     * @var string Path to factories directory.
     */
    public $factoriesPath = '@tests/unit/factories';
    /**
     * @var string Language to use when generating fixtures data.
     */
    public $language;
    /**
     * @var \Faker\Generator Faker generator instance
     */
    private $_generator;
    /**
     * The model definitions in the container.
     *
     * @var array
     */
    protected $definitions = [];

    public function init()
    {
        $factoriesPath = $this->factoriesPath ? Yii::getAlias($this->factoriesPath) : null;
        if ($factoriesPath) {
            $this->load($factoriesPath);
        }
    }

    /**
     * Load factories from paths.
     * @param string $path
     * @return $this
     */
    public function load($path)
    {
        if (!is_dir($path)) {
            throw new InvalidParamException("Factories path \"$path\" must be a dir.");
        }
        $factory = $this;
        $files = FileHelper::findFiles($path, ['only' => ['*.php']]);
        foreach ($files as $file) {
            require $file;
        }
        return $factory;
    }

    /**
     * Define a class with a given short-name.
     * @param string $class
     * @param string $name
     * @param callable $attributes
     */
    public function defineAs($class, $name, callable $attributes)
    {
        $this->define($class, $attributes, $name);
    }

    /**
     * Define a class with a given set of attributes.
     * @param  string $class
     * @param  callable $attributes
     * @param  string $name
     */
    public function define($class, callable $attributes, $name = 'default')
    {
        $this->definitions[$class][$name] = $attributes;
    }

    /**
     * Create an instance of the given model and persist it to the database.
     * @param  string $class
     * @param  array $attributes
     * @return mixed
     */
    public function create($class, array $attributes = [])
    {
        return $this->of($class)->create($attributes);
    }

    /**
     * Create an instance of the given model and type and persist it to the database.
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     * @return mixed
     */
    public function createAs($class, $name, array $attributes = [])
    {
        return $this->of($class, $name)->create($attributes);
    }

    /**
     * @param $class
     * @param $count
     * @param array $attributes
     * @return \yii\db\ActiveRecord[]
     */
    public function createMultiple($class, $count, array $attributes = [])
    {
        return $this->of($class)->createMultiple($count, $attributes);
    }

    /**
     * Create an instance of the given model.
     * @param  string $class
     * @param  array $attributes
     * @return mixed
     */
    public function make($class, array $attributes = [])
    {
        return $this->of($class)->make($attributes);
    }

    /**
     * Create an instance of the given model and type.
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     * @return mixed
     */
    public function makeAs($class, $name, array $attributes = [])
    {
        return $this->of($class, $name)->make($attributes);
    }

    /**
     * Create an instance of the given model.
     * @param  string $class
     * @param $count
     * @param  array $attributes
     * @return mixed
     */
    public function makeMultiple($class, $count, array $attributes = [])
    {
        return $this->of($class)->makeMultiple($count, $attributes);
    }

    /**
     * Get the raw attribute array for a given named model.
     * @param  string $class
     * @param  string $name
     * @param  array $attributes
     * @return array
     */
    public function rawOf($class, $name, array $attributes = [])
    {
        return $this->raw($class, $attributes, $name);
    }

    /**
     * Get the raw attribute array for a given model.
     * @param  string $class
     * @param  array $attributes
     * @param  string $name
     * @return array
     */
    public function raw($class, array $attributes = [], $name = 'default')
    {
        $raw = call_user_func($this->definitions[$class][$name], $this->getGenerator());
        return array_merge($raw, $attributes);
    }

    /**
     * Create a builder for the given model.
     *
     * @param  string $class
     * @param  string $name
     * @return FactoryBuilder
     */
    public function of($class, $name = 'default')
    {
        return new FactoryBuilder($class, $name, $this->definitions, $this->getGenerator());
    }

    /**
     * Returns Faker generator instance. Getter for private property.
     *
     * @return \Faker\Generator
     */
    public function getGenerator()
    {
        if ($this->_generator === null) {
            $language = $this->language === null ? Yii::$app->language : $this->language;
            $this->_generator = \Faker\Factory::create(str_replace('-', '_', $language));
        }
        return $this->_generator;
    }

    /**
     * Determine if the given offset exists.
     * @param  string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->definitions[$offset]);
    }

    /**
     * Get the value of the given offset.
     * @param  string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->make($offset);
    }

    /**
     * Set the given offset to the given value.
     * @param  string $offset
     * @param  callable $value
     */
    public function offsetSet($offset, $value)
    {
        $this->define($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     * @param  string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->definitions[$offset]);
    }
}
