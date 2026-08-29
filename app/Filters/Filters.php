<?php
/**
 * Created by Eslam Salah.
 * User: Eslam Salah
 * Date: 26/04/2026
 * Time: 2:00 PM
 */

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Filters
{
    public $request;

    public $filterAttributes;

    protected $var_filters = [];


    /**
     * @var Builder $builder
     */
    protected $builder;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
        if($request)
        {
            $this->filterAttributes = $request->only($this->var_filters);
        }
    }

    public function apply($builder)
    {
        $this->builder = $builder;
        foreach ($this->getFilterAttr() as $name=>$value)
        {
            if (method_exists($this,$name))
            {
                $this->$name($value);
            }
        }
        return $this->builder;
    }

    public function getFilterAttr()
    {
        return array_filter($this->filterAttributes,function ($value){
            return $value !== null;
        });
    }
    public function setFilterAttr($array)
    {
        $this->filterAttributes=$array;
    }

    static function fromArray($array)
    {
        $instance = new static(null);
        $instance->filterAttributes = $array;
        return $instance;
    }

}
