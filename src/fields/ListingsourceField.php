<?php
namespace kuriousagency\listingsource\fields;

use kuriousagency\listingsource\Listingsource;
use kuriousagency\listingsource\assetbundles\field\FieldAssetBundle;
use kuriousagency\listingsource\assetbundles\fieldsettings\FieldSettingsAssetBundle;
use kuriousagency\listingsource\services\ListingsourceService;
use kuriousagency\listingsource\base\Link;
// use kuriousagency\listingsource\models\Email;
// use kuriousagency\listingsource\models\Phone;
// use kuriousagency\listingsource\models\Url;
use kuriousagency\listingsource\models\Entry;
use kuriousagency\listingsource\models\Category;
use kuriousagency\listingsource\models\Channel;
use kuriousagency\listingsource\models\Group;
// use kuriousagency\listingsource\models\Asset;
// use kuriousagency\listingsource\models\Product;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Json as JsonHelper;
use craft\helpers\Db as DbHelper;
use yii\db\Schema;
use yii\base\ErrorException;
use craft\validators\ArrayValidator;

class ListingsourceField extends Field
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering field types.
     */
    const EVENT_REGISTER_LINKIT_LINK_TYPES = 'registerListingsourceLinkTypes';

    // Private Properties
    // =========================================================================

    private $_availableLinkTypes;
    private $_enabledLinkTypes;
    private $_columnType = Schema::TYPE_TEXT;


    //  Properties
    // =========================================================================

    public $selectLinkText = '';
    public $types;
    public $allowCustomText;
    public $defaultText;
    public $allowTarget;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('listingsource', 'Listingsource');
    }

    public static function defaultSelectLinkText(): string
    {
        return Craft::t('listingsource', 'Select source type...');
    }

    // Public Methods
    // =========================================================================

    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['types'], ArrayValidator::class, 'min' => 1, 'tooFew' => Craft::t('listingsource', 'You must select at least one source type.'), 'skipOnEmpty' => false];
        return $rules;
    }

    public function getContentColumnType(): string
    {
        return $this->_columnType;
    }

    public static function hasContentColumn(): bool
    {
        return true;
    }

    public function normalizeValue($value, ElementInterface $element = null)
    {
        if($value instanceof Link)
        {
            return $value;
        }

        if(is_string($value))
        {
            $value = JsonHelper::decodeIfJson($value);
        }

        // Handle any Craft2 content
        // if(!isset($value['value']))
        // {
        //     $value = $this->_normalizeValueCraft2($value);
        // }

        $link = null;
        if(isset($value['type']) && $value['type'] != '')
        {
            if(isset($value['values']))
            {
                $postedValue = $value['values'][$value['type']] ?? '';
                $value['value'] = is_array($postedValue) ? $postedValue[0] : $postedValue;
                unset($value['values']);
            }

            $link = $this->_getLinkTypeModelByType($value['type']);
            $link->setAttributes($value, false); // TODO: Get Rules added for these and remove false
        }

        return $link;
    }

    public function serializeValue($value, ElementInterface $element = null)
    {
        $serialized = [];
        if($value instanceof Link)
        {
            $serialized = [
                'type' => $value->type,
                'value' => $value->value,
                'customText' => $value->customText,
                'target' => $value->target,
            ];
        }

        return parent::serializeValue($serialized, $element);
    }

    public function getSettingsHtml()
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(FieldSettingsAssetBundle::class);

        return $view->renderTemplate(
            'listingsource/fields/_settings',
            [
                'field' => $this,
            ]
        );
    }

    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();

        // Register our asset bundle
        $view->registerAssetBundle(FieldAssetBundle::class);

        // Get our id and namespace
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);

        // Javascript
        $jsVariables = JsonHelper::encode([
            'id' => $namespacedId,
            'name' => $this->handle,
        ]);
        $view->registerJs('new Garnish.LinkitField('.$jsVariables.');');

        // Render the input template
        return $view->renderTemplate(
            'listingsource/fields/_input',
            [
                'id' => $id,
                'name' => $this->handle,
                'field' => $this,
                'element' => $element,
                'currentLink' => $value,
            ]
        );
    }




    // public function getInputHtml($value, ElementInterface $element = null): string
    // {
    //     $view = Craft::$app->getView();

    //     // Register our asset bundle
    //     $view->registerAssetBundle(FieldAssetBundle::class);

    //     // Get our id and namespace
    //     $id = $view->formatInputId($this->handle);
    //     $namespacedId = $view->namespaceInputId($id);

    //     $settings = $this->getSettings();

    //     // Javascript
    //     $jsVariables = JsonHelper::encode([
    //         'id' => $namespacedId,
    //         'name' => $this->handle,
    //     ]);
    //     $view->registerJs('new Garnish.ListingsourceField('.$jsVariables.');');

    //     // Render the input template
    //     // return $view->renderTemplate(
    //     //     'listingsource/fields/_input',
    //     //     [
    //     //         'id' => $id,
    //     //         'name' => $this->handle,
    //     //         'field' => $this,
    //     //         'element' => $element,
    //     //         'currentLink' => $value,
    //     //     ]
    //     // );
        
    //     // echo "<pre>";
    //     // print_r($settings);
    //     // echo "</pre>";
    //     // exit();

    //     $sections = CRAFT::$app->sections->getAllSections();    
    //     $options = [];

    //     // if($this->element->hasDescendants()){
    //     //     $options[$this->element->section->handle] = 'Children';
    //     // }

    //     foreach($sections as $section) {
    //         $options[$section->handle] = $section->name;
    //     }


    //     $value = ['section' => ""];    
    //     $types = ['all' => 'All'];
      
    //     if($value['section']){
	//         $criteria = craft()->elements->getCriteria(ElementType::Category);
	// 		$criteria->group = $value['section'];

	//         foreach($criteria->find() as $cat){
	// 	        $types[$cat->id] = $cat->title;
	//         }
	        
	//         /*foreach(craft()->sections->getSectionByHandle($value['section'])->getEntryTypes() as $type){
	// 	        $types[$type->handle] = $type->name;
	//         }*/
    //     }

    //     return $view->renderTemplate(
    //         'listingsource/fields/ListingSourceFieldType.twig',
    //         [
    //             'id' => $id,
    //             'name' => $this->handle,
    //             'field' => $this,       
    //             'values' => $value,         
    //             'options' => $options,
    //             'types' => $types,
    //             // 'element' => $element,
    //             // 'currentLink' => $value,
    //         ]
    //     );
    // }

    public function getElementValidationRules(): array
    {
        return ['validateLinkValue'];
    }

    public function isValueEmpty($value, ElementInterface $element): bool
    {
        return empty($value->value ?? '');
    }

    public function validateLinkValue(ElementInterface $element)
    {
        $fieldValue = $element->getFieldValue($this->handle);
        if(!$fieldValue->validate())
        {
            $element->addModelErrors($fieldValue, $this->handle);
        }
    }

    public function getSearchKeywords($value, ElementInterface $element): string
    {
        if($value)
        {
            return $value->getText();
        }
        return '';
    }

    public function getTableAttributeHtml($value, ElementInterface $element): string
    {
        if($value)
        {
            return $value->getLink(false) ?? '';
        }
        return '';
    }

    public function getAvailableLinkTypes()
    {
        if(is_null($this->_availableLinkTypes))
        {
            $linkTypes = Listingsource::$plugin->service->getAvailableLinkTypes();

            if($linkTypes)
            {
                foreach ($linkTypes as $linkType)
                {
                   $this->_availableLinkTypes[] = $this->_populateLinkTypeModel($linkType);
                }
            }
        }

        return $this->_availableLinkTypes;
    }

    public function getEnabledLinkTypes()
    {
        if(is_null($this->_enabledLinkTypes))
        {
            $this->_enabledLinkTypes = [];
            if(is_array($this->types))
            {
                foreach ($this->types as $type => $settings)
                {
                    if($settings['enabled'] ?? false) {
                        $linkType = $this->_getLinkTypeModelByType($type);
                        if($linkType)
                        {
                            $this->_enabledLinkTypes[] = $linkType;
                        }
                    }
                }
            }
        }
        return $this->_enabledLinkTypes;
    }

    public function getEnabledLinkTypesAsOptions()
    {
        $options = [];
        $enabledLinkTypes = $this->getEnabledLinkTypes();
        if($enabledLinkTypes)
        {
            $options = [
                [
                    'label' => $this->selectLinkText != '' ? $this->selectLinkText : static::defaultSelectLinkText(),
                    'value' => '',
                ],
            ];

            foreach ($enabledLinkTypes as $enabledLinkType) {
                $options[] = [
                    'label' => $enabledLinkType->label,
                    'value' => $enabledLinkType->type,
                ];
            }
        }

        return $options;
    }

    // Private Methods
    // =========================================================================

    private function _getLinkTypeModelByType(string $type, bool $populate = true)
    {
        try {
            $linkType = Craft::createObject($type);
            if($populate)
            {
                $linkType = $this->_populateLinkTypeModel($linkType);
            }
            return $linkType;
        } catch(ErrorException $exception) {
            $error = $exception->getMessage();
            return false;
        }
    }

    private function _populateLinkTypeModel(Link $linkType)
    {
        // Get Type Settings
        $attributes = $this->types[$linkType->type] ?? [];
        $linkType->setAttributes($attributes, false);
        $linkType->fieldSettings = $this->getSettings();
        return $linkType;
    }

    /*
    private function _normalizeValueCraft2($content)
    {
        if(!$content)
        {
            return null;
        }

        $newContent = [
            'customText' => $content['customText'] ?? null,
            'target' => ($content['target'] ?? false) ? true : false,
        ];

        if($content['type'])
        {
            switch ($content['type'])
            {
                case 'email':
                    $newContent['type'] = Email::class;
                    $newContent['value'] = $content['email'] ?? '';
                    break;

                case 'custom':
                    $newContent['type'] = Url::class;
                    $newContent['value'] = $content['custom'] ?? '';
                    break;

                case 'tel':
                    $newContent['type'] = Phone::class;
                    $newContent['value'] = $content['tel'] ?? '';
                    break;

                case 'entry':
                    $newContent['type'] = Entry::class;
                    $newContent['value'] = $content['entry'][0] ?? '';
                    break;

                case 'category':
                    $newContent['type'] = Category::class;
                    $newContent['value'] = $content['category'][0] ?? '';
                    break;

                case 'asset':
                    $newContent['type'] = Asset::class;
                    $newContent['value'] = $content['asset'][0] ?? '';
                    break;

                case 'product':
                    $newContent['type'] = Product::class;
                    $newContent['value'] = $content['product'][0] ?? '';
                    break;

                default:
                    return $content;
                    break;
            }
        }

        return $newContent;
    }
    */
}
