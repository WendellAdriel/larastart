<?php
/** Larastart
 *
 * (The MIT license)
 * Copyright (c) 2017 andrealeixo.com
 */

namespace Larastart\Template\Model;

use Larastart\Resource\Model\ModelInterface;
use Larastart\Template\TemplateAbstract;

class ModelTemplate extends TemplateAbstract
{
    protected $model = null;
    protected $defaultTemplatePath = __DIR__.DIRECTORY_SEPARATOR."Model.php.template";

    public function __construct(ModelInterface $model, string $storagePath, string $templatePath = null)
    {
        $this->model           = $model;
        $this->templatePath    = $templatePath ?: $this->defaultTemplatePath;
        $this->storagePath     = $this->buildStoragePath($storagePath);
        $this->storageFileName = $model->getName().".php";
    }

    public function render(string $contents = ''):string
    {
        $contents     = $contents ?: $this->loadTemplate();
        $replacePairs = array(
            '!!traits!!'          => $this->getTraits($this->model),
            '!!className!!'       => $this->model->getName(),
            '!!tableValue!!'      => strtolower($this->model->getTable()),
            '!!timestampsValue!!' => $this->model->useTimestamps() ? 'true' : 'false',
            '!!dates!!'           => $this->getDates($this->model),
            '!!relationships!!'   => $this->getRelationships($this->model),
        );
        return strtr($contents, $replacePairs);
    }

    protected function getRelationships(ModelInterface $model)
    {
        // All relationships may contain an array or a string
        // Array: several relationships of that type
        // string: the name of the only resource

        $output = [];
        $output[] = $this->handleRelationship($model->getHasOne(), "hasOne");
        $output[] = $this->handleRelationship($model->getHasMany(), "hasMany");
        $output[] = $this->handleRelationship($model->getBelongsTo(), "belongsTo");
        $output[] = $this->handleRelationship($model->getBelongsToMany(), "belongsToMany");

        return implode("\n\n\t", array_filter($output));
    }

    protected function handleRelationship($relationship, $type)
    {
        // Hacking a bit.. :\
        $output = [];
        $type = "get".strtoupper($type);
        if (is_array($relationship)) {
            foreach ($relationship as $resourceName) {
                $output[]= $this->$type($resourceName);
            }
        } elseif (is_string($relationship)) {
            $output[]= $this->$type($relationship);
        }
        return implode("\n\n\t", $output);
    }


    protected function getTraits(ModelInterface $model)
    {
        $traits = [];
        if ($model->useSoftDeletes()) {
            $traits[]= 'use \Illuminate\Database\Eloquent\SoftDeletes;';
        }
        return implode("\n", $traits);
    }

    protected function getDates(ModelInterface $model)
    {
        $output = [];
        if ($model->useSoftDeletes()) {
            $output[]= 'deleted_at';
        }
        if ($model->useTimestamps()) {
            $output[]= 'created_at';
            $output[]= 'updated_at';
        }
        return 'protected $dates = ["'.implode("', '", $output).'"];';
    }

    protected function getHasOne(string $name)
    {
        return 'public function '.$this->makeFunctionCase($name).'()
    {
        return $this->hasOne('.$this->makeCamelCase($name).'::class);
    }';
    }

    protected function getHasMany(string $name)
    {
        return 'public function '.$this->makeFunctionCase($name).'s()
    {
        return $this->hasMany('.$this->makeCamelCase($name).'::class);
    }';
    }

    protected function getBelongsTo(string $name)
    {
        return 'public function '.$this->makeFunctionCase($name).'()
    {
        return $this->belongsTo('.$this->makeCamelCase($name).'::class);
    }';
    }

    protected function getBelongsToMany(string $name)
    {

        // Plural is added using "s". Assuming what Laravel assumes in
        // guessing the foreign key and so on
        return 'public function '.$this->makeFunctionCase($name).'s()
    {
        return $this->belongsToMany('.$this->makeCamelCase($name).'::class);
    }';
    }

    protected function makeCamelCase($name)
    {
        return str_replace(" ", "", ucwords(str_replace(["-", "_"], " ", strtolower($name))));
    }

    protected function makeFunctionCase($name)
    {
        return lcfirst($this->makeCamelCase($name));

    }

    /**
     * Builds the path to create the model
     *
     * @param string $storagePath
     * @return string
     */
    protected function buildStoragePath(string $storagePath): string
    {
        $defaultStoragePath = DIRECTORY_SEPARATOR . "App" . DIRECTORY_SEPARATOR . "Domains" .
            DIRECTORY_SEPARATOR . $this->model->getName() . DIRECTORY_SEPARATOR . "Models";

        if (realpath($storagePath) === false) {
            return getcwd() . DIRECTORY_SEPARATOR . $storagePath . $defaultStoragePath;
        }

        return realpath($storagePath) . $defaultStoragePath;
    }
}
