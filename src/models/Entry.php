<?php
/**
 * Listing Source plugin for Craft CMS 3.x
 *
 * listing entries, categories, etc.
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\listingsource\models;

use kuriousagency\listingsource\ListingSource;

use Craft;
use craft\base\Model;
use craft\base\ElementInterface;
use craft\elements\Entry as CraftEntry;
use craft\helpers\Json;
use craft\validators\ArrayValidator;

/**
 * @author    Kurious Agency
 * @package   ListingSource
 * @since     2.0.0
 */
class Entry extends Model
{
	// Public Properties
    // =========================================================================

    /**
     * @var string
     */
	public $sources;
	public $value;
	public $attribute;
	public $order;
	public $total;
	public $pagination = false;
	public $sticky;
	public $featured;

	private $_element;
	private $_parent;

    // Public Methods
    // =========================================================================

	public function getName()
	{
		return 'Entry';
	}
	
	public function getType()
	{
		//return get_class($this);
		return (new \ReflectionClass($this))->getShortName();
	}

	public function getClass()
	{
		return get_class($this);
	}

	public function getElementType()
	{
		return CraftEntry::class;
	}

	public function hasSettings()
	{
		return true;
	}

	public function getElement()
	{
		if (!$this->_element) {
			if ($this->value){
				$this->_element = Craft::$app->getEntries()->getEntryById((int) $this->realValue);
			}
		}
		return $this->_element;
	}

	public function getRealValue()
	{
		if (is_array($this->value)) {
			if (array_key_exists($this->type, $this->value)) {
				$this->value = $this->value[$this->type];
				if (is_array($this->value)) {
					$this->value = $this->value[0];
				}
			} else {
				$this->value = null;
			}
		}
		return $this->value;
	}

	public function getStickyElements()
	{
		if ($this->sticky) {
			$query = CraftEntry::find();
			$query->id = $this->sticky;
			return $query->all();
		}

		return [];
	}

	public function getParent()
	{
		if (!$this->_parent) {
			$this->_parent = $this->getElement() ? $this->getElement()->section : null;
		}

		return $this->_parent;
	}

	public function getItems($criteria = null)
	{
		$query = CraftEntry::find();
		$query->descendantOf = $this->getElement()->id;
		$query->descendantDist = 1;
		
		$query->limit = null;
		if ($this->total) {
			$query->limit = $this->total;
		}
		if ($this->attribute != 'userDefined') {
			$query->orderBy = $this->attribute . ' ' . $this->order;
		} else if ($this->order == 'desc') {
			$query->inReverse = true;
		}
		if ($this->sticky) {
			$query->id = array_merge(['not'], $this->sticky);
			if ($this->total) {
				$query->limit = $this->total - count($this->sticky);
			}
			$ids = $query->ids();

			$query = CraftEntry::find();
			if ($this->total) {
				$query->limit = $this->total;
			}
			$query->id = array_merge($this->sticky, $ids);
			$query->fixedOrder = true;
		}
		if ($criteria) {
			Craft::configure($query, $criteria);
		}
		return $query;
	}

	public function getSourceOptions($sources=[])
	{
		$types = [];
		$criteria = CraftEntry::find();
		if ($sources != '*') {
			$criteria->section = $sources;
		}

		foreach ($criteria->all() as $type)
		{
			$types[] = [
				'label' => $type->title,
				'value' => $type->id,
			];
		}
		return $types;
	}

	public function getSourceAttributes($model)
	{
		//Craft::dd($group);
		/*if ($group) {
			$group = Craft::$app->getSections()->getSectionByHandle($group)->entryTypes[0];
		} else {*/
			$group = $model->getElement() ? $model->getElement()->type : null;
		//}
		//Craft::dd($group);
		
		$attributes = [
			'userDefined' => 'User Defined',
			'title' => 'Title',
			'postDate' => 'Date',
		];
		if ($group) {
			foreach ($group->fields as $field)
			{
				$attributes[$field->handle] = $field->name;
			}
		}
		return $attributes;
	}

	public function getSourceTypes()
	{
		$types = [];
		foreach (Craft::$app->getSections()->getAllSections() as $type)
		{
			$types[] = [
				'label' => $type->name,
				'value' => $type->uid,
				'handle' => $type->handle,
			];
		}
		return $types;
	}

	public function getInputHtml($field, $model, $selected=false): string
	{
		$view = Craft::$app->getView();

		if ($model && $model->type == $this->type) {
			$this->value = $model->value ?? null;
		}
		
		$id = $view->formatInputId($field->handle);
		$namespacedId = $view->namespaceInputId($id);

		$settings = $field->getSettings();
		$elementSettings = $settings['types'][$this->class];
		$sources = $elementSettings['sources'] == "" ? "*" : $elementSettings['sources'];

		if ($sources != '*') {
			foreach ($sources as $key => $source)
			{
				$sources[$key] = 'section:'.$source;
			}
		}
		
		$jsonVars = [
            'id' => $id,
            'name' => $field->handle,
            'namespace' => $namespacedId,
            'prefix' => $view->namespaceInputId(''),
            ];
        $jsonVars = Json::encode($jsonVars);
		$view->registerJs("$('#{$namespacedId}-field').ListingSourceField(" . $jsonVars . ");");
		
		// Render the input template
        return $view->renderTemplate(
            'listingsource/_components/types/input/_element',
            [
                'name' => $field->handle.'[value]['.$this->type.']',
				'value' => $this->realValue,
				'elements' => [$this->getElement()],
				'elementType' => CraftEntry::class,
				'type' => $this->type,
				'class' => $this->class,
				'sources' => $sources == '*' ? null : $sources,
                'id' => $id.'-'.str_replace("\\","-",$this->type).'-element',
				'namespacedId' => $namespacedId,
				'selected' => $selected,
				'attribute' => $model->attribute ?? null,
            ]
        );
	}

	

	public function getStickyParams($model)
	{
		$view = Craft::$app->getView();

		return [
			'elementType' => CraftEntry::class,
			'sources' => ['section:'.($model->element->section->uid ?? 'null')],
			'criteria' => ['descendantOf'=>($model->element->id ?? null), 'descendantDist'=>1],
		];
	}

	public function rules()
	{
		$rules = [
            [['value'], 'required']
        ];
        return $rules;
	}

	public function getErrors($attribute = NULL)
	{
		$errors = [];
		if (!$this->realValue && (($attribute && $attribute == 'value') || !$attribute)) {
			$errors['value'] = ['Please select an entry'];
		}
		return $errors;
	}

	public function serializeValue($value, ElementInterface $element = null)
    {
		return [
			'type' => $this->getType(),
			'value' => $this->value,
			'attribute' => $this->attribute,
			'order' => $this->order,
			'total' => $this->total,
			'pagination' => $this->pagination,
			'sticky' => $this->sticky,
			'featured' => $this->featured,
		];
    }
}
