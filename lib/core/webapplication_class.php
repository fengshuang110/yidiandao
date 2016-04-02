<?php
/**
 * @copyright Copyright(c) 2015 aircheng.com
 * @file webapplication_class.php
 * @brief web应用类
 * @author nswe
 * @date 2015/10/26 18:35:19
 * @version 4.2
 */

/**
 * @brief IWebApplication 应用类
 * @class IWebApplication
 * @note
 */
class IWebApplication extends IApplication
{
	public $clientType;               //客户端类型, pc电脑, mobile手机
	public $controller;               //当前控制器对象
	public $webRunPath;               //运行时的WEB虚拟目录
	public $defaultViewDir = 'views'; //默认视图目录
	public $defaultSkinDir = 'skin';  //默认皮肤目录

    /**
     * @brief 构造函数
     * @param array or string $config 配置数组或者配置文件名称
     */
	public function __construct($config)
	{
		parent::__construct($config);

		if(!$this->basePath)
		{
			if(isset($_SERVER['SCRIPT_FILENAME']))
			{
				$this->basePath = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR;
			}
			else
			{
				//document_root 不存在
				if(!isset($_SERVER['DOCUMENT_ROOT']))
				{
					if(isset($_SERVER['PATH_TRANSLATED']))
					{
						$_SERVER['DOCUMENT_ROOT'] = dirname($_SERVER['PATH_TRANSLATED']);
					}
				}
				$this->basePath = rtrim(rtrim($_SERVER['DOCUMENT_ROOT'],'\\/').dirname($_SERVER['SCRIPT_NAME']),'\\/').DIRECTORY_SEPARATOR;
			}
		}

		if(!$this->basePath)
		{
			throw new IException("the APP basePath illegal");
		}

		$this->clientType = IClient::getDevice();
		ini_set('upload_tmp_dir',$this->getRuntimePath());
		ini_set('session.save_path',$this->basePath.'backup');
		$this->defaultViewDir = isset($this->config['viewPath']) ? $this->config['viewPath'] : $this->defaultViewDir;
		$this->defaultSkinDir = isset($this->config['skinPath']) ? $this->config['skinPath'] : $this->defaultSkinDir;
		$this->disableMagicQuotes();
	}

    /**
     * @brief 请求执行方法，是application执行的入口方法
     */
    public function execRequest()
    {
        IUrl::beginUrl();
        $ctrlId   = IUrl::getInfo("controller");
        $actionId = IUrl::getInfo('action');

        $this->controller = $this->createController($ctrlId);
        IInterceptor::run("onCreateController",$this->controller);
        $this->controller->run($actionId);
		IInterceptor::run("onFinishController");
    }
    /**
     * @brief 创建当前的Controller对象
     * @param string $ctrlId 控制器ID
     * @return object Controller对象
     */
    public function createController($ctrlId)
    {
    	$ctrlId     = $ctrlId ? $ctrlId : $this->defaultController;
    	$ctrlObject = null;
    	$ctrlFile   = $this->basePath."controllers/".$ctrlId.".php";

        if(is_file($ctrlFile) && class_exists($ctrlId))
        {
        	$ctrlObject = new $ctrlId($this,$ctrlId);
        	if($ctrlObject instanceof IController == false)
        	{
        		$ctrlObject = null;
        	}
        }
        $this->controller = $ctrlObject ? $ctrlObject : new IController($this,$ctrlId);
        return $this->controller;
    }
    /**
     * @brief 取得当前的Controller
     * @return object Controller对象
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @brief 获取当前WEB的运行URL路径
     * @return String 路径地址
     */
	public function getWebRunPath()
	{
		if($this->webRunPath == null)
		{
			$this->webRunPath = IUrl::creatUrl('').'runtime'."/";
		}
		return $this->webRunPath;
	}

    /**
     * @brief 获取视图实际路径
     * @return String 实际路径
     */
	public function getViewPath()
	{
		return $this->getBasePath().$this->defaultViewDir.DIRECTORY_SEPARATOR;
	}

    /**
     * @brief 获取当前WEB的模板URL路径
     * @return String 路径地址
     */
	public function getWebViewPath()
	{
		return IUrl::creatUrl('').$this->defaultViewDir."/";
	}

    /**
     * @brief 取消魔法转义
     */
    public function disableMagicQuotes()
    {
		if(ini_get("magic_quotes_gpc"))
		{
			if(isset($_POST))
			{
				$_POST = $this->stripslashes($_POST);
			}

			if(isset($_GET))
			{
				$_GET = $this->stripslashes($_GET);
			}

			if(isset($_COOKIE))
			{
				$_COOKIE = $this->stripslashes($_COOKIE);
			}

			if(isset($_REQUEST))
			{
				$_REQUEST = $this->stripslashes($_REQUEST);
			}
		}
    }

    /**
     * @brief 辅助disableMagicQuotes();
     */
    private function stripslashes($arr)
    {
    	if(is_array($arr))
		{
			foreach($arr as $key => $value)
			{
				$arr[$key] = $this->stripslashes($value);
			}
			return $arr;
		}
		else
		{
			return stripslashes($arr);
		}
    }
}