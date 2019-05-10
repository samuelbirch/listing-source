<?php
/**
 * Listing Source plugin for Craft CMS 3.x
 *
 * listing entries, categories, etc.
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\listingsource\controllers;

use kuriousagency\listingsource\ListingSource;

use Craft;
use craft\web\Controller;

/**
 * @author    Kurious Agency
 * @package   ListingSource
 * @since     2.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionSticky()
    {
		$request = Craft::$app->getRequest();
		$handle = $request->getRequiredBodyParam('handle');
		$type = $request->getRequiredBodyParam('type');
		$value = $request->getRequiredBodyParam('value');

		$model = new $type();
		$model->value = $value;
		//Craft::dd($model);

		return $this->asJson($model->getStickyParams($model));
	}
	
	public function actionAttributes()
	{
		$request = Craft::$app->getRequest();
		$type = $request->getRequiredBodyParam('type');
		$value = $request->getRequiredBodyParam('value');

		$model = new $type();
		$model->value = $value;

		return $this->asJson($model->getSourceAttributes($model));
	}

}
