<?php

namespace ddinchev\factorii;

use ArrayAccess;
use Yii;
use yii\base\Component;
use yii\base\InvalidParamException;
use yii\base\Object;
use yii\di\Instance;
use yii\helpers\FileHelper;

class Factorii extends Component implements ArrayAccess
{
    /**
     * @var \yii\db\Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the Factorii object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     */
    public $db = 'db';
    /**
     * @var string Path to factories directory.
     */
    public $factoriesPath = '@tests/unit/factories';
    /**
     * @var string Language to use when generating fixtures data.
     */
    public $language;
    /**
     * @var bool By default disable integrity checks when creating records.
     */
    public $disableIntegrityChecks = true;
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
        $this->db = Instance::ensure($this->db, Object::className());
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
        $factorii = $this;
        $files = FileHelper::findFiles($path, ['only' => ['*.php']]);
        foreach ($files as $file) {
            require $file;
        }
        return $factorii;
    }

    /**
     * Deleted all the data for all defined (user or unused) factories.
     */
    public function flushAll() {
        foreach (array_keys($this->definitions) as $modelClass) {
            $this->resetTable($modelClass);
        }
    }

    /**
     * Removes all existing data from the specified table and resets sequence number to 1 (if any).
     * This method is called before populating fixture data into the table associated with this fixture.
     * @param string $modelClass should be subclass of \yii\db\ActiveRecord
     */
    public function resetTable($modelClass)
    {
        $tableName = $modelClass::tableName();
        $table = $this->db->getTableSchema($tableName);
        $this->db->createCommand()->delete($table->fullName)->execute();
        if ($table->sequenceName !== null) {
            $this->db->createCommand()->resetSequence($table->fullName, 1)->execute();
        }
    }

    /**
     * Define a class with a given set of base attributes.
     * @param string $class
     * @param callable $attributes
     * @param string $alias
     */
    public function define($class, callable $attributes, $alias = 'default')
    {
        $this->definitions[$class][$alias] = $attributes;
    }

    /**
     * Create an instance of the given model and persist it to the database.
     * @param string $class
     * @param array $attributes
     * @param string $alias
     * @return mixed
     */
    public function create($class, array $attributes = [], $alias = 'default')
    {
        return $this->of($class, $alias)->create($attributes);
    }

    /**
     * @param $class
     * @param $count
     * @param array $attributes
     * @return \yii\db\ActiveRecord[]
     */
    public function createList($class, $count, array $attributes = [])
    {
        return $this->of($class)->createList($count, $attributes);
    }

    /**
     * Create an instance of the given model.
     * @param string $class
     * @param array $attributes
     * @param string $alias
     * @return \yii\db\ActiveRecord
     */
    public function build($class, array $attributes = [], $alias = 'default')
    {
        return $this->of($class, $alias)->build($attributes);
    }

    /**
     * Create an instance of the given model.
     * @param  string $class
     * @param $count
     * @param  array $attributes
     * @return mixed
     */
    public function buildList($class, $count, array $attributes = [])
    {
        return $this->of($class)->buildList($count, $attributes);
    }

    /**
     * Get the raw attribute array for a given model.
     * @param  string $class
     * @param  array $attributes
     * @param  string $alias
     * @return array
     */
    public function attributes($class, array $attributes = [], $alias = 'default')
    {
        $raw = call_user_func($this->definitions[$class][$alias], $this->getGenerator());
        return array_merge($raw, $attributes);
    }

    /**
     * Create a builder for the given model.
     *
     * @param  string $class
     * @param  string $alias
     * @return FactoryBuilder
     */
    public function of($class, $alias = 'default')
    {
        return new FactoryBuilder($class, $alias, $this->definitions, $this->getGenerator());
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
        return $this->build($offset);
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
