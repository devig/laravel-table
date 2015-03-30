<?php namespace Gbrock\Table;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;

class Column {
    /** @var Model The base model from which we can gather certain options */
    protected $model;

    /** @var string Applicable database field used in sorting */
    protected $field;

    /** @var string The default sorting direction */
    protected $direction;

    /** @var string The visible portion of the column header */
    protected $label;

    /** @var bool Whether this column can be sorted by the user */
    protected $sortable = false;

    /**
     * @var closure
     * A rendering closure used when generating cell data, accepts the model:
     * $column->setRenderer(function($model){ return '<strong>' . $model->id . '</strong>'; })
     */
    protected $renderer;

    public static function create()
    {
        $args = func_get_args();

        $class = new static;

        // Detect instantiation scheme
        switch(count($args))
        {
            case 1: // one argument passed
                if(is_string($args[0]))
                {
                    // Only the field was passed
                    $class->setField($args[0]);
                    $class->setLabel(ucwords(str_replace('_', ' ', $args[0])));
                }
                elseif(is_array($args[0]))
                {
                    // Just an array was sent; set the parameters.
                    $class->setParameters($args);
                }
                break;
            case 2: // two arguments
                if(is_string($args[0]) && is_string($args[1]))
                {
                    // Both are strings, this is a Field => Label affair.
                    $class->setField($args[0]);
                    $class->setLabel($args[1]);
                }
                elseif(is_string($args[0]) && is_array($args[1]))
                {
                    // Normal complex initialization: field and quick parameters
                    $class->setField($args[0]);
                    $class->setParameters($args[1]);
                    if(!isset($args[1]['label']))
                    {
                        $class->setLabel(ucwords(str_replace('_', ' ', $args[0])));
                    }
                }
                break;
            case 3: // three arguments
                if(is_string($args[0]) && is_string($args[1]) && is_callable($args[2]))
                {
                    // Field, Label, and [rendering] Closure.  Standard View addition.
                    $class->setField($args[0]);
                    $class->setLabel($args[1]);
                    $class->setRenderer($args[2]);
                }
                break;
        }

        return $class;
    }

    /**
     * Sets some common-sense options based on the underlying data model.
     *
     * @param Model $model
     */
    public function setOptionsFromModel($model)
    {
        if($model->is_sortable && in_array($this->getField(), $model->getSortable()))
        {
            // The model dictates that this column should be sortable
            $this->setSortable(true);
        }

        $this->model = $model;
    }

    /**
     * Checks if this column is currently being sorted.
     */
    public function isSorted()
    {
        if(Request::input(config('gbrock-tables.key_field')) == $this->getField())
        {
            return true;
        }

        if(!Request::input(config('gbrock-tables.key_field')) && $this->model && $this->model->getSortingField() == $this->getField())
        {
            // No sorting was requested, but this is the default field.
            return true;
        }

        return false;
    }

    /**
     * Generates a URL to toggle sorting by this column.
     */
    public function getSortURL($direction = false)
    {
        if(!$direction)
        {
            // No direction indicated, determine automatically from defaults.
            $direction = $this->getDirection();

            if($this->isSorted())
            {
                // If we are already sorting by this column, swap the direction
                $direction = $direction == 'asc' ? 'desc' : 'asc';
            }
        }

        // Generate and return a URL which may be used to sort this column
        return $this->generateUrl(array_filter([
            config('gbrock-tables.key_field') => $this->getField(),
            config('gbrock-tables.key_direction') => $direction,
        ]));
    }

    /**
     * Returns the default sorting
     * @return string
     */
    public function getDirection()
    {
        if($this->isSorted())
        {
            // If the column is currently being sorted, grab the direction from the query string
            $this->direction = Request::input(config('gbrock-tables.key_direction'));
        }

        if(!$this->direction)
        {
            $this->direction = config('gbrock-tables.default_direction');
        }

        return $this->direction;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @return boolean
     */
    public function isSortable()
    {
        return $this->sortable;
    }

    /**
     * @param boolean $sortable
     */
    public function setSortable($sortable)
    {
        $this->sortable = (bool) $sortable;
    }

    public function generateUrl($parameters = [])
    {
        // Generate our needed parameters
        $parameters = array_merge($this->getCurrentInput(), $parameters);

        // Grab the current URL
        $route = URL::getRequest()->route();

        return url($route->getUri() . '?' . http_build_query($parameters));
    }

    protected function getCurrentInput()
    {
        return Input::only([
            config('gbrock-tables.key_field') => Request::input(config('gbrock-tables.key_field')),
            config('gbrock-tables.key_direction') => Request::input(config('gbrock-tables.key_direction')),
        ]);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function setParameters($arguments)
    {
        foreach($arguments as $k => $v)
        {
            $this->{'set' . ucfirst($k)}($v);
        }
    }

    /**
     * @param string $direction
     */
    public function setDirection($direction)
    {
        $this->direction = $direction;
    }

    public function render($data)
    {
        if($this->hasRenderer())
        {
            $renderer = $this->renderer;
            return $renderer($data);
        }
    }

    public function hasRenderer()
    {
        return ($this->renderer != null);
    }

    public function setRenderer($function)
    {
        if(!is_callable($function))
        {
            throw new CallableFunctionNotProvidedException;
        }

        $this->renderer = $function;
    }
}