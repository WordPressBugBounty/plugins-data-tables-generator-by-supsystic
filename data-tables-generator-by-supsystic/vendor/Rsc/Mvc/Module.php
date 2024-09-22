<?php


class RscDtgs_Mvc_Module
{

    /**
     * @var RscDtgs_Environment
     */
    private $environment;

    /**
     * @var string
     */
    private $location;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var RscDtgs_Http_Request
     */
    private $request;

    /**
     * @var RscDtgs_Mvc_Controller
     */
    private $controller;

    /**
     * @var bool
     */
    private $overloadController;

    /**
     * Constructor.
     * @param RscDtgs_Environment $environment An instance of the current environment
     * @param string          $location    Full path to the directory where is module
     * @param string          $namespace   Module prefix
     */
    public function __construct(RscDtgs_Environment $environment, $location, $namespace)
    {
        $this->environment = $environment;
        $this->location    = $location;
        $this->namespace   = $namespace;

        $this->overloadController = false;
    }

    /**
     * @param string $method The name of the method
     * @param array $arguments An array of arguments
     * @return mixed
     * @throws BadMethodCallException If specified method does not exists
     */
    public function __call($method, $arguments)
    {
        if (!method_exists($this->environment, $method)) {
            throw new BadMethodCallException(sprintf('Unexpected method: %s', $method));
        }

        return call_user_func_array(array($this->environment, $method), $arguments);
    }

    /**
     * Returns an instance of the current environment
     * @return RscDtgs_Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Returns full path to the directory where is module
     * @return string
     */
    public function getLocation()
    {
        return str_replace('\\', '/', $this->location);
    }

    /**
     * Returns the url to the current module's page or NULL if the menu page is not configured
     * @return null|string
     */
    public function getUrl()
    {
        if (null !== $url = $this->environment->getUrl()) {
            return $url . '&module=' . end(explode('_', strtolower(basename($this->location))));
        }

        return null;
    }

    /**
     * Returns the URL to the module location
     * @return string
     */
    public function getLocationUrl()
    {
		$path = plugin_basename($this->location);
		return plugins_url($path);
    }

    /**
     * Returns the name of the current module
     * @return string
     */
    public function getModuleName()
    {
        return strtolower(basename($this->location));
    }

    /**
     * Returns an instance of controller
     * @return null|RscDtgs_Mvc_Controller
     */
    public function getController()
    {
        if ($this->controller === null) {
            $this->createController();
        }

        return $this->controller;
    }

    /**
     * Returns the HTTP request to the module
     * @return RscDtgs_Http_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set HTTP request to the module
     * @param RscDtgs_Http_Request $request HTTP request
     * @return RscDtgs_Mvc_Module
     */
    public function setRequest(RscDtgs_Http_Request $request)
    {
        $this->request = $request;
        return $this;
    }

	public function handle()
	{
		$action = $this->request->query->get('action', 'index') . 'Action';
		$welcomePageShowed = $this->environment->getConfig()->get('welcome_page_was_showed');
		$promoController = $this->environment->getConfig()->get('promo_controller');

		if ($promoController && class_exists($promoController) && !$welcomePageShowed && $this->getModuleName() != 'license') {
			$promoController = new $promoController($this->environment, $this->request);
			if (method_exists($promoController, 'indexAction')) {
				return call_user_func_array(
					array($promoController, 'indexAction'),
					array($this->request)
				);
			}
		} else {
			if (method_exists($this->getController(), $action)) {
				return call_user_func_array(array($this->getController(), $action), array(
					$this->request
				));
			}
		}

		$twig = $this->environment->getTwig();

		return RscDtgs_Http_Response::create()->setContent($twig->render('404.twig', array(
			'controller' => $this->getModuleName(),
			'action' => $action,
		)));
	}

    /**
     * Triggered when the resolver doing initialization of module
     */
    public function onInit()
    {

    }

    /**
     * Triggered on plugin activation
     */
    public function onInstall()
    {

    }

    /**
     * Triggered on plugin uninstall
     */
    public static function onUninstall()
    {

    }

    /**
     * Triggered on plugin deactivation
     */
    public function onDeactivation()
    {

    }

    /**
     * @param $overloadController
     *
     * @return $this
     */
    public function setOverloadController($overloadController)
    {
        $this->overloadController = (bool)$overloadController;

        return $this;
    }

    public function getOverloadController()
    {
        return $this->overloadController;
    }

    /**
     * Creates controllers.
     *
     * @return bool
     */
    private function createController()
    {
        $config = $this->getEnvironment()->getConfig();

        $prefix = $this->namespace;
        $path   = $this->location;
        $module = ucfirst($this->getModuleName());

		$filterPathName = 'mvc_module_path_' . $this->environment->getPluginName() . '_' . $prefix;
		$filterPrefixName = 'mvc_module_prefix_' . $this->environment->getPluginName() . '_' . $prefix;

		if($this->overloadController){
			if(!has_filter($filterPathName) && !has_filter($filterPrefixName)){
				$prefix = $config->get('pro_modules_prefix');
				$path   = $config->get('pro_modules_path')
					. '/'
					. $prefix
					. '/'
					. $module;
			}else{
				$prefix = apply_filters($filterPrefixName, $prefix);
				$path = apply_filters($filterPathName, $path);
			}
		}

        $classname = $prefix . '_' . $module . '_Controller';

        if (!is_file($path . '/Controller.php') || !class_exists($classname)) {
            return false;
        }

        $this->controller = new $classname($this->environment, $this->request);

        return true;
    }

}
