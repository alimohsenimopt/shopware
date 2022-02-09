<?php

declare(strict_types=1);
/**
 * Enlight
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://enlight.de/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@shopware.de so we can send you a copy immediately.
 *
 * @category   Enlight
 * @copyright  Copyright (c) 2011, shopware AG (http://www.shopware.de)
 * @license    http://enlight.de/license     New BSD License
 */

/**
 * Enlight
 *
 * This file has been ported from Zend Framework 1 into the Enlight Framework,
 * to allow the removal of the original library from Shopware.
 *
 * This porting is in full compliance with the New BSD License
 * under which the original file is distributed.
 *
 * @category   Enlight
 * @package    Enlight_Config_BaseConfig
 */
class Enlight_Config_BaseConfig implements Countable, Iterator
{
    /**
     * Whether in-memory modifications to configuration data are allowed
     *
     * @var bool
     */
    protected $_allowModifications;

    /**
     * Iteration index
     *
     * @var int
     */
    protected $_index;

    /**
     * Number of elements in configuration data
     *
     * @var int
     */
    protected $_count;

    /**
     * Contains array of configuration data
     *
     * @var array<string|int, mixed>
     */
    protected $_data;

    /**
     * Used when unsetting values during iteration to ensure we do not skip
     * the next element
     *
     * @var bool
     */
    protected $_skipNextIteration;

    /**
     * Contains which config file sections were loaded. This is null
     * if all sections were loaded, a string name if one section is loaded
     * and an array of string names if multiple sections were loaded.
     *
     * @var string[]|string|null
     */
    protected $_loadedSection;

    /**
     * This is used to track section inheritance. The keys are names of sections that
     * extend other sections, and the values are the extended sections.
     *
     * @var array
     */
    protected $_extends = [];

    /**
     * Load file error string.
     *
     * Is null if there was no error while file loading
     *
     * @var string
     */
    protected $_loadFileErrorStr = null;

    /**
     * Enlight_Config_BaseConfig provides a property based interface to
     * an array. The data are read-only unless $allowModifications
     * is set to true on construction.
     *
     * Enlight_Config_BaseConfig also implements Countable and Iterator to
     * facilitate easy access to the data.
     *
     * @param bool $allowModifications
     */
    public function __construct(array $array, $allowModifications = false)
    {
        $this->_allowModifications = (bool) $allowModifications;
        $this->_loadedSection = null;
        $this->_index = 0;
        $this->_data = [];
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $this->_data[$key] = new self($value, $this->_allowModifications);
            } else {
                $this->_data[$key] = $value;
            }
        }
        $this->_count = \count($this->_data);
    }

    /**
     * Retrieve a value and return $default if there is no element set.
     *
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function get($name, $default = null)
    {
        $result = $default;
        if (\array_key_exists($name, $this->_data)) {
            $result = $this->_data[$name];
        }

        return $result;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Only allow setting of a property if $allowModifications
     * was set to true on construction. Otherwise, throw an exception.
     *
     * @param string     $name
     * @param mixed|null $value
     *
     * @throws Enlight_Config_Exception
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if ($this->_allowModifications) {
            if (\is_array($value)) {
                $this->_data[$name] = new self($value, true);
            } else {
                $this->_data[$name] = $value;
            }
            $this->_count = \count($this->_data);
        } else {
            throw new Enlight_Config_Exception('Enlight_Config_BaseConfig is read only');
        }
    }

    /**
     * Deep clone of this instance to ensure that nested Enlight_Config_BaseConfigs
     * are also cloned.
     *
     * @return void
     */
    public function __clone()
    {
        $array = [];
        foreach ($this->_data as $key => $value) {
            if ($value instanceof Enlight_Config_BaseConfig) {
                $array[$key] = clone $value;
            } else {
                $array[$key] = $value;
            }
        }
        $this->_data = $array;
    }

    /**
     * Return an associative array of the stored data.
     *
     * @return array
     */
    public function toArray()
    {
        $array = [];
        $data = $this->_data;
        foreach ($data as $key => $value) {
            if ($value instanceof Enlight_Config_BaseConfig) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Support isset() overloading on PHP 5.1
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->_data[$name]);
    }

    /**
     * Support unset() overloading on PHP 5.1
     *
     * @param string $name
     *
     * @throws Enlight_Config_Exception
     *
     * @return void
     */
    public function __unset($name)
    {
        if ($this->_allowModifications) {
            unset($this->_data[$name]);
            $this->_count = \count($this->_data);
            $this->_skipNextIteration = true;
        } else {
            throw new Enlight_Config_Exception('Enlight_Config_BaseConfig is read only');
        }
    }

    /**
     * Defined by Countable interface
     *
     * @return int
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->_count;
    }

    /**
     * Defined by Iterator interface
     *
     * @return mixed can return any value
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        $this->_skipNextIteration = false;

        return current($this->_data);
    }

    /**
     * Defined by Iterator interface
     *
     * @return mixed can return any value
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->_data);
    }

    /**
     * Defined by Iterator interface
     *
     * @return void
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function next()
    {
        if ($this->_skipNextIteration) {
            $this->_skipNextIteration = false;

            return;
        }
        next($this->_data);
        ++$this->_index;
    }

    /**
     * Defined by Iterator interface
     *
     * @return void
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->_skipNextIteration = false;
        reset($this->_data);
        $this->_index = 0;
    }

    /**
     * Defined by Iterator interface
     *
     * @return bool
     *
     * @deprecated - Native return type will be added with Shopware 5.8
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->_index < $this->_count;
    }

    /**
     * Returns the section name(s) loaded.
     *
     * @return string[]|string|null
     */
    public function getSectionName()
    {
        if (\is_array($this->_loadedSection) && \count($this->_loadedSection) == 1) {
            $this->_loadedSection = $this->_loadedSection[0];
        }

        return $this->_loadedSection;
    }

    /**
     * Returns true if all sections were loaded
     *
     * @return bool
     */
    public function areAllSectionsLoaded()
    {
        return $this->_loadedSection === null;
    }

    /**
     * Merge another Enlight_Config_BaseConfig with this one. The items
     * in $merge will override the same named items in
     * the current config.
     *
     * @return Enlight_Config_BaseConfig
     */
    public function merge(Enlight_Config_BaseConfig $merge)
    {
        foreach ($merge as $key => $item) {
            if (\array_key_exists($key, $this->_data)) {
                if ($item instanceof Enlight_Config_BaseConfig && $this->$key instanceof Enlight_Config_BaseConfig) {
                    $this->$key = $this->$key->merge(new Enlight_Config_BaseConfig($item->toArray(), !$this->readOnly()));
                } else {
                    $this->$key = $item;
                }
            } else {
                if ($item instanceof Enlight_Config_BaseConfig) {
                    $this->$key = new Enlight_Config_BaseConfig($item->toArray(), !$this->readOnly());
                } else {
                    $this->$key = $item;
                }
            }
        }

        return $this;
    }

    /**
     * Prevent any more modifications being made to this instance. Useful
     * after merge() has been used to merge multiple Enlight_Config_BaseConfig objects
     * into one object which should then not be modified again.
     *
     * @return void
     */
    public function setReadOnly()
    {
        $this->_allowModifications = false;
        foreach ($this->_data as $value) {
            if ($value instanceof Enlight_Config_BaseConfig) {
                $value->setReadOnly();
            }
        }
    }

    /**
     * Returns if this Enlight_Config_BaseConfig object is read only or not.
     *
     * @return bool
     */
    public function readOnly()
    {
        return !$this->_allowModifications;
    }

    /**
     * Get the current extends
     *
     * @return array
     */
    public function getExtends()
    {
        return $this->_extends;
    }

    /**
     * Set an extend for Enlight_Config_Writer_Writer
     *
     * @param string $extendingSection
     * @param string $extendedSection
     *
     * @return void
     */
    public function setExtend($extendingSection, $extendedSection = null)
    {
        if ($extendedSection === null && isset($this->_extends[$extendingSection])) {
            unset($this->_extends[$extendingSection]);
        } elseif ($extendedSection !== null) {
            $this->_extends[$extendingSection] = $extendedSection;
        }
    }

    /**
     * Throws an exception if $extendingSection may not extend $extendedSection,
     * and tracks the section extension if it is valid.
     *
     * @param string $extendingSection
     * @param string $extendedSection
     *
     * @throws Enlight_Config_Exception
     *
     * @return void
     */
    protected function _assertValidExtend($extendingSection, $extendedSection)
    {
        // detect circular section inheritance
        $extendedSectionCurrent = $extendedSection;
        while (\array_key_exists($extendedSectionCurrent, $this->_extends)) {
            if ($this->_extends[$extendedSectionCurrent] == $extendingSection) {
                throw new Enlight_Config_Exception('Illegal circular inheritance detected');
            }
            $extendedSectionCurrent = $this->_extends[$extendedSectionCurrent];
        }
        // remember that this section extends another section
        $this->_extends[$extendingSection] = $extendedSection;
    }

    /**
     * Handle any errors from simplexml_load_file or parse_ini_file
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     *
     * @return void
     */
    protected function _loadFileErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if ($this->_loadFileErrorStr === null) {
            $this->_loadFileErrorStr = $errstr;
        } else {
            $this->_loadFileErrorStr .= (PHP_EOL . $errstr);
        }
    }

    /**
     * Merge two arrays recursively, overwriting keys of the same name
     * in $firstArray with the value in $secondArray.
     *
     * @param mixed $firstArray  First array
     * @param mixed $secondArray Second array to merge into first array
     *
     * @return array
     */
    protected function _arrayMergeRecursive($firstArray, $secondArray)
    {
        if (\is_array($firstArray) && \is_array($secondArray)) {
            foreach ($secondArray as $key => $value) {
                if (isset($firstArray[$key])) {
                    $firstArray[$key] = $this->_arrayMergeRecursive($firstArray[$key], $value);
                } else {
                    if ($key === 0) {
                        $firstArray = [0 => $this->_arrayMergeRecursive($firstArray, $value)];
                    } else {
                        $firstArray[$key] = $value;
                    }
                }
            }
        } else {
            $firstArray = $secondArray;
        }

        return $firstArray;
    }
}
