<?php
/**
 * @brief 系统模块
 * @class System
 * @note  后台
 */
class System extends IController
{
	public $checkRight  = array('check' => 'all','uncheck' => array('default','admin_repwd','admin_repwd_act','navigation','navigation_update','navigation_del','navigation_edit','navigation_recycle','navigation_recycle_del','navigation_recycle_restore'));
	public $layout      = 'admin';

	public function init()
	{
		IInterceptor::reg('CheckRights@onCreateAction');
	}

	//邮件发送测试
	function test_sendmail()
	{
		$site_config                 = array();
		$site_config['email_type']   = IReq::get('email_type');
		$site_config['mail_address'] = IReq::get('mail_address');
		$site_config['smtp']         = IReq::get('smtp');
		$site_config['smtp_user']    = IReq::get('smtp_user');
		$site_config['smtp_pwd']     = IReq::get('smtp_pwd');
		$site_config['smtp_port']    = IReq::get('smtp_port');
		$site_config['email_safe']   = IReq::get('email_safe');
		$test_address                = IReq::get('test_address');

		$smtp = new SendMail($site_config);
		if($error = $smtp->getError())
		{
			$result = array('isError'=>true,'message' => $error);
		}
		else
		{
			$title    = 'email test';
			$content  = '您好，这是来自iWebShop系统的测试邮件，如果您能收到此邮件那么恭喜您，系统邮件服务正常。';
			if($smtp->send($test_address,$title,$content))
			{
				$result = array('isError'=>false,'message' => '恭喜你！测试通过');
			}
			else
			{
				$result = array('isError'=>true,'message' => $smtp->getError());
			}
		}
		echo JSON::encode($result);
	}

	//列出控制器
	function list_controller()
	{
		$planPath = $this->app->getBasePath().'controllers';
		$planList = array();
		$dirRes   = opendir($planPath);

		while($dir = readdir($dirRes))
		{
			if(!in_array($dir,array('.','..','.svn')))
			{
				$planList[] = basename($dir,'.php');
			}
		}
		echo JSON::encode($planList);
	}

	//列出某个控制器的action动作和视图
	function list_action()
	{
		$ctrlId     = IReq::get('ctrlId');
		if($ctrlId != '')
		{
			$baseContrl = get_class_methods('IController');
			$advContrl  = get_class_methods($ctrlId);
			$diffArray  = array_diff($advContrl,$baseContrl);
			echo JSON::encode($diffArray);
		}
	}

	/**
	 * @brief 配送方式修改
	 */
    public function delivery_edit()
	{
		$data = array();
        $id = IFilter::act(IReq::get('id'),'int');

        if($id)
        {
            $delivery = new IModel('delivery');
            $data = $delivery->getObj('id = '.$id);
		}

		//获取省份
		$areaData = array();
		$areaDB = new IModel('areas');
		$areaList = $areaDB->query('parent_id = 0');
		foreach($areaList as $val)
		{
			$areaData[$val['area_id']] = $val['area_name'];
		}
		$this->areaList  = $areaList;
		$this->data_info = $data;
		$this->area      = $areaData;
        $this->redirect('delivery_edit');
	}

	/**
	 * @brief 配送方式删除和还原
	 */
	public function delivery_operate()
	{
		$id = IReq::get('id');
		$op = IReq::get('op');
        if(is_array($id)) $id = implode(',',$id);

        if(empty($id))
        {
        	if($op == 'del' || $op == 'recover')
        	{
        		$this->redirect('delivery_recycle',false);
        	}
        	else
        	{
        		$this->redirect('delivery',false);
        	}
        	Util::showMessage('请选择要操作的选项');
        	exit;
        }

		$delivery     =  new IModel('delivery');
		$deliveryData = $delivery->query('id in ('.$id.')','name');
		$deliveryName = array();
		foreach($deliveryData as $val)
		{
			$deliveryName[] = $val['name'];
		}

		$logObj = new log('db');

		//物理删除
		if($op=='del')
		{
			$delivery->del('id in('.$id.')');

			$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"删除了回收站中的配送方式","被删除的配送方式为：".join(',',$deliveryName)));

			$this->redirect('delivery_recycle');
		}
		else if($op =='recover')//还原
		{
			$delivery->setData(array('is_delete'=>0));
			if($delivery->update('id in('.$id.')'))
			{
				$logObj->write('operation',array('管理员:'.$this->admin['admin_name'],'恢复了回收站中的配送方式','被恢复的配送方式为：'.join(',',$deliveryName)));
			}

			$this->redirect('delivery_recycle');
		}
		else//逻辑删除
		{
			$delivery->setData(array('is_delete'=>1));
			if($delivery->update('id in('.$id.')'))
			{
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"把配送方式移除到回收站中","被移除到回收站中的配送方式为：".join(',',$deliveryName)));
			}

			$this->redirect('delivery');
		}
	}

	/**
	 * 配送方式修改
	 */
    public function delivery_update()
    {
        $delivery = new IModel('delivery');
        //ID
        $id   = IFilter::act(IReq::get('id'),'int');
		//配送方式名称
		$name = IFilter::act(IReq::get('name'));
		//类型
		$type = IFilter::act(IReq::get('type'),'int');
        //首重重量
        $first_weight = IFilter::act(IReq::get('first_weight'),'float');
        //续重重量
        $second_weight = IFilter::act(IReq::get('second_weight'),'float');
        //首重价格
        $first_price = IFilter::act(IReq::get('first_price'),'float');
        //续重价格
        $second_price = IFilter::act(IReq::get('second_price'),'float');
        //是否支持物流保价
        $is_save_price = IFilter::act(IReq::get('is_save_price'),'int');
        //地区费用类型
        $price_type = IFilter::act(IReq::get('price_type'),'int');
        //启用默认费用
        $open_default = IFilter::act(IReq::get('open_default'),'int');
        //支持的配送地区ID
        $area_groupid = serialize(IReq::get('area_groupid'));
        //配送地址对应的首重价格
        $firstprice = serialize(IReq::get('firstprice'));
        //配送地区对应的续重价格
        $secondprice = serialize(IReq::get('secondprice'));
        //排序
        $sort = IFilter::act(IReq::get('sort'),'int');
        //状态
        $status = IFilter::act(IReq::get('status'),'int');
        //描述
        $description = IFilter::act(IReq::get('description'),'text');
        //保价费率
        $save_rate = IFilter::act(IReq::get('save_rate'),'float');
        //最低保价
        $low_price = IFilter::act(IReq::get('low_price'),'float');

        $data = array(
        	'name'         => $name,
        	'type'         => $type,
        	'first_weight' => $first_weight,
        	'second_weight'=> $second_weight,
        	'first_price'  => $first_price,
        	'second_price' => $second_price,
        	'is_save_price'=> $is_save_price,
        	'price_type'   => $price_type,
        	'open_default' => $open_default,
        	'area_groupid' => $area_groupid,
        	'firstprice'   => $firstprice,
        	'secondprice'  => $secondprice,
        	'sort'         => $sort,
        	'status'       => $status,
        	'description'  => $description,
        	'save_rate'    => $save_rate,
        	'low_price'    => $low_price,
        );
        $delivery->setData($data);
        $logObj = new log('db');

		if($id=="")
		{
			if($delivery->add())
			{
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"添加了配送方式",'添加的配送方式为：'.$name));
			}
		}
		else
		{
			if($delivery->update('id = '.$id))
			{
				$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了配送方式",'修改的配送方式为：'.$name));
			}
		}
		$this->redirect('delivery');
    }

   /**
    * 添加/修改支付方式插件
    */
    function payment_edit()
    {
        $payment_id = IFilter::act(IReq::get("id"),'int');
        $paymentRow = array();
        $paymentObj = new IModel('payment');
    	$paymentRow = $paymentObj->getObj("id = ".$payment_id);
    	if($paymentRow)
    	{
	        $this->paymentRow = $paymentRow;
	        $this->redirect('payment_edit');
    	}

        if(!$paymentRow)
        {
        	IError::show(403,"支付方式不存在");
        }
    }

 	/**
     * @brief 更新支付方式插件
     */
    function payment_update()
    {
    	//获取Post数据
    	$payment_id    = IFilter::act(IReq::get("id"),'int');
    	$name          = IFilter::act(IReq::get("name"));
    	$poundage_type = IFilter::act(IReq::get("poundage_type"),'int');
    	$poundage      = IFilter::act(IReq::get("poundage"),'float');
        $order         = IFilter::act(IReq::get("order"),'int');
        $note          = IFilter::act(IReq::get('note'),'text');
        $status        = IFilter::act(IReq::get('status'),'int');
        $client_type   = IFilter::act(IReq::get('client_type'),'int');
        $config_param  = array();

        $paymentInstance = Payment::createPaymentInstance($payment_id);
        $configParam     = $paymentInstance->configParam();
        foreach($configParam as $key => $val)
        {
			$config_param[$key] = IFilter::act(IReq::get($key));
        }
        $config_param = JSON::encode($config_param);

        $updateData = array(
        	'name'          => $name,
        	'poundage_type' => $poundage_type,
        	'poundage'      => $poundage,
        	'order'         => $order,
        	'note'          => $note,
        	'status'        => $status,
        	'config_param'  => $config_param,
        	'client_type'   => $client_type,
        );

        $paymentDB = new IModel('payment');
        $paymentDB->setData($updateData);
        $paymentDB->update('id = '.$payment_id);

        //日志记录
		$logObj = new log('db');
		$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改了支付方式",'修改的支付方式为：'.$name));

		$this->redirect('payment_list');
    }
	//[网站管理][站点设置]保存
	function save_conf()
	{
		if(!$_POST)
		{
			$this->redirect('conf_base');
		}

		//错误信息
		$form_index = IReq::get('form_index');
		switch($form_index)
		{
			case "base_conf":
			{
				if(isset($_FILES['logo']['name']) && $_FILES['logo']['name']!='')
				{
					$uploadObj = new PhotoUpload();
					$uploadObj->setIterance(false);
					$photoInfo = $uploadObj->run();
					if(isset($photoInfo['logo']['img']) && file_exists($photoInfo['logo']['img']))
					{
						$_POST['logo'] = $photoInfo['logo']['img'];
					}
				}
			}
			break;

			case "site_footer_conf":
			{
				$_POST['site_footer_code']=preg_replace('![\\r\\n]+!',"",$_POST['site_footer_code']);
			}
			break;

			case "index_slide":
			{
				$config_slide = array();
				if(isset($_POST['slide_name']))
				{
					foreach($_POST['slide_name'] as $key => $value)
					{
						$config_slide[$key]['name'] = $value;
						$config_slide[$key]['url']  = $_POST['slide_url'][$key];
						$config_slide[$key]['img']  = $_POST['slide_img'][$key];
					}
				}

				if( isset($_FILES['slide_pic']) )
				{
					$uploadObj = new PhotoUpload();
					$uploadObj->setIterance(false);
					$slideInfo = $uploadObj->run();

					if(isset($slideInfo['slide_pic']['flag']))
					{
						$slideInfo['slide_pic'] = array($slideInfo['slide_pic']);
					}

					if(isset($slideInfo['slide_pic']))
					{
						foreach($slideInfo['slide_pic'] as $key=>$value)
						{

							if($value['flag']==1)
							{
								$config_slide[$key]['img']=$value['img'];
							}
						}
					}

				}
				$_POST = array('index_slide' => serialize( $config_slide ));
			}
			break;

			//导航写入数据库，不需要记录配置文件
			case "guide_conf":
			{
				$guideName = IFilter::act(IReq::get('guide_name'));
				$guideLink = IFilter::act(IReq::get('guide_link'));
				$data      = array();

				$guideObj = new IModel('guide');

				if(!empty($guideName))
				{
					foreach($guideName as $key => $val)
					{
						if(!empty($val) && !empty($guideLink[$key]))
						{
							$data[$key]['name'] = $val;
							$data[$key]['link'] = $guideLink[$key];
						}
					}
				}

				//清空导航栏
				$guideObj->del('all');

				if($data)
				{
					//插入数据
					foreach($data as $order => $rs)
					{
						$dataArray = array(
							'order' => $order,
							'name'  => $rs['name'],
							'link'  => $rs['link'],
						);
						$guideObj->setData($dataArray);
						$guideObj->add();
					}

					//跳转方法
					$this->redirect('conf_base',false);
					exit;
				}
			}
			break;

			case "other_conf":
			{
				if( isset($_POST['auto_finish']) && $_POST['auto_finish']=="" )
				{
					$_POST['auto_finish']=="0";
				}
			}
			break;

			case "mail_conf":
			break;
			case "system_conf":
			break;
			case "service_online":
			{
				$serviceName = IFilter::act(IReq::get('service_name'));
				$serviceQQ   = IFilter::act(IReq::get('service_qq'));
				$data        = array();
				foreach($serviceName as $key => $val)
				{
					$data[] = array('name' => $serviceName[$key],'qq' => $serviceQQ[$key]);
				}
				$_POST = array('service_online' => serialize( $data ));
			}
			break;
		}

		//获取输入的数据
		$this->confRow = $inputArray = $_POST;

		if($form_index == 'system_conf')
		{
			//写入的配置文件
			$configFile = IWeb::$app->getBasePath().'config/config.php';
			Config::edit($configFile,$inputArray);
		}
		else
		{
			$siteObj = new Config('site_config');
			$siteObj->write($inputArray);
		}
		$this->redirect('conf_base',false);
	}

	//[权限管理][管理员]管理员添加，修改[单页]
	function admin_edit()
	{
		$id =IFilter::act( IReq::get('id'),'int' );
		if($id)
		{
			$adminObj = new IModel('admin');
			$where = 'id = '.$id;
			$this->adminRow = $adminObj->getObj($where);
		}
		$this->redirect('admin_edit');
	}

	//[权限管理][管理员]检查admin_user唯一性
	function check_admin($name = null,$id = null)
	{
		//php校验$name!=null , ajax校验 $name == null
		$admin_name = ($name==null) ? IReq::get('admin_name','post') : $name;
		$admin_id   = ($id==null)   ? IReq::get('admin_id','post')   : $id;
		$admin_name = IFilter::act($admin_name);
		$admin_id = intval($id);


		$adminObj = new IModel('admin');
		if($admin_id)
		{
			$where = 'admin_name = "'.$admin_name.'" and id != '.$admin_id;
		}
		else
		{
			$where = 'admin_name = "'.$admin_name.'"';
		}

		$adminRow = $adminObj->getObj($where);

		if(!empty($adminRow))
		{
			if($name != null)
			{
				return false;
			}
			else
			{
				echo '-1';
			}
		}
		else
		{
			if($name != null)
			{
				return true;
			}
			else
			{
				echo '1';
			}
		}
	}

	//[权限管理][管理员]管理员添加，修改[动作]
	function admin_edit_act()
	{
		$id = IFilter::act( IReq::get('id','post') );
		$adminObj = new IModel('admin');

		//错误信息
		$message = null;

		$dataArray = array(
			'id'         => $id,
			'admin_name' => IFilter::string( IReq::get('admin_name','post') ),
			'role_id'    => IFilter::act( IReq::get('role_id','post') ),
			'email'      => IFilter::string( IReq::get('email','post') ),
		);

		//检查管理员name唯一性
		$isPass = $this->check_admin($dataArray['admin_name'],$id);
		if($isPass == false)
		{
			$message = $dataArray['admin_name'].'管理员已经存在,请更改名字';
		}

		//提取密码 [ 密码设置 ]
		$password   = IReq::get('password','post');
		$repassword = IReq::get('repassword','post');

		//修改操作
		if($id)
		{
			if($password != null || $repassword != null)
			{
				if($password == null || $repassword == null || $password != $repassword)
				{
					$message = '密码不能为空,并且二次输入的必须一致';
				}
				else
					$dataArray['password'] = md5($password);
			}

			//有错误
			if($message != null)
			{
				$this->adminRow = $dataArray;
				$this->redirect('admin_edit',false);
				Util::showMessage($message);
			}
			else
			{
				$where = 'id = '.$id;
				$adminObj->setData($dataArray);
				$adminObj->update($where);

				//修改为自身密码时
				if($id == $this->admin['admin_id'])
				{
					//同步更新safe
					ISafe::set('admin_name',$dataArray['admin_name']);
					ISafe::set('admin_pwd',$dataArray['password']);
				}
			}
		}
		//添加操作
		else
		{
			if($password == null || $repassword == null || $password != $repassword)
			{
				$message = '密码不能为空,并且二次输入的必须一致';
			}
			else
				$dataArray['password'] = md5($password);

			if($message != null)
			{
				$this->adminRow = $dataArray;
				$this->redirect('admin_edit',false);
				Util::showMessage($message);
			}
			else
			{
				$dataArray['create_time'] = ITime::getDateTime();
				$adminObj->setData($dataArray);
				$adminObj->add();
			}
		}
		$this->redirect('admin_list');
	}

	//[权限管理][管理员]管理员更新操作[回收站操作][物理删除]
	function admin_update()
	{
		$id = IFilter::act( IReq::get('id') ,'int' );

		if($id == 1 || (is_array($id) && in_array(1,$id)))
		{
			$this->redirect('admin_list',false);
			Util::showMessage('不允许删除系统初始化管理员');
		}

		//是否为回收站操作
		$isRecycle = IReq::get('recycle');

		if(!empty($id))
		{
			$obj   = new IModel('admin');
			$where = Util::joinStr($id);

			if($isRecycle === null)
			{
				$obj->del($where);
				$this->redirect('admin_recycle');
			}
			else
			{
				//回收站操作类型
				$is_del = ($isRecycle == 'del') ? 1 : 0;
				$obj->setData(array('is_del' => $is_del));
				$obj->update($where);
				$this->redirect('admin_list');
			}
		}
		else
		{
			if($isRecycle == 'del')
				$this->redirect('admin_list',false);
			else
				$this->redirect('admin_recycle',false);

			Util::showMessage('请选择要操作的管理员ID');
		}
	}

	//[权限管理][角色] 角色更新操作[回收站操作][物理删除]
	function role_update()
	{
		$id = IFilter::act( IReq::get('id'),'int' );

		//是否为回收站操作
		$isRecycle = IReq::get('recycle');

		if(!empty($id))
		{
			$obj   = new IModel('admin_role');
			$where = Util::joinStr($id);

			if($isRecycle === null)
			{
				$obj->del($where);
				$this->redirect('role_recycle');
			}
			else
			{
				//回收站操作类型
				$is_del    = ($isRecycle == 'del') ? 1 : 0;
				$obj->setData(array('is_del' => $is_del));
				$obj->update($where);
				$this->redirect('role_list');
			}
		}
		else
		{
			if($isRecycle == 'del')
				$this->redirect('role_list',false);
			else
				$this->redirect('role_recycle',false);

			Util::showMessage('请选择要操作的角色ID');
		}
	}

	//[权限管理][角色] 角色修改,添加 [单页]
	function role_edit()
	{
		$id = IFilter::act( IReq::get('id'),'int' );
		if($id)
		{
			$adminObj = new IModel('admin_role');
			$where = 'id = '.$id;
			$this->roleRow = $adminObj->getObj($where);
		}

		//获取权限码分组形势
		$rightObj  = new IModel('right');
		$rightData = $rightObj->query('is_del = 0','*','name','asc');

		$rightArray     = array();
		$rightUndefined = array();
		foreach($rightData as $key => $item)
		{
			preg_match('/\[.*?\]/',$item['name'],$localPre);
			if(isset($localPre[0]))
			{
				$arrayKey = trim($localPre[0],'[]');
				$rightArray[$arrayKey][] = $item;
			}
			else
			{
				$rightUndefined[]      = $item;
			}
		}

		$this->rightArray     = $rightArray;
		$this->rightUndefined = $rightUndefined;

		$this->redirect('role_edit');
	}

	//[权限管理][角色] 角色修改,添加 [动作]
	function role_edit_act()
	{
		$id = IFilter::act( IReq::get('id','post') );
		$roleObj = new IModel('admin_role');

		//要入库的数据
		$dataArray = array(
			'id'     => $id,
			'name'   => IFilter::string( IReq::get('name','post') ),
			'rights' => null,
		);

		//检查权限码是否为空
		$rights = IFilter::act( IReq::get('right','post') );
		if(empty($rights) || $rights[0]=='')
		{
			$this->roleRow = $dataArray;
			$this->redirect('role_edit',false);
			Util::showMessage('请选择要分配的权限');
		}

		//拼接权限码
		$rightsArray = array();
		$rightObj    = new IModel('right');
		$rightList   = $rightObj->query('id in ('.join(",",$rights).')','`right`');
		foreach($rightList as $key => $val)
		{
			$rightsArray[] = trim($val['right'],',');
		}

		$dataArray['rights'] = empty($rightsArray) ? '' : ','.join(',',$rightsArray).',';
		$roleObj->setData($dataArray);
		if($id)
		{
			$where = 'id = '.$id;
			$roleObj->update($where);
		}
		else
		{
			$roleObj->add();
		}
		$this->redirect('role_list');
	}

	//[权限管理][权限] 权限修改，添加[单页]
	function right_edit()
	{
		$id = IFilter::act( IReq::get('id'),'int' );
		if($id)
		{
			$adminObj = new IModel('right');
			$where = 'id = '.$id;
			$this->rightRow = $adminObj->getObj($where);
		}

		$this->redirect('right_edit');
	}

	//[权限管理][权限] 权限修改，添加[动作]
	function right_edit_act()
	{
		$id    = IFilter::act( IReq::get('id','post') );
		$right = IFilter::act( array_unique(IReq::get('right')) );
		$name  = IFilter::act( IReq::get('name','post') );

		if(!$right)
		{
			$this->rightRow = array(
				'id'   => $id,
				'name' => $name,
			);
			$this->redirect('right_edit',false);
			Util::showMessage('权限码不能为空');
			exit;
		}

		$dataArray = array(
			'id'    => $id,
			'name'  => $name,
			'right' => join(',',$right),
		);

		$rightObj = new IModel('right');
		$rightObj->setData($dataArray);
		if($id)
		{
			$where = 'id = '.$id;
			$rightObj->update($where);
		}
		else
		{
			$rightObj->add();
		}
		$this->redirect('right_list');
	}

	//[权限管理][权限] 权限更新操作 [回收站操作][物理删除]
	function right_update()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		//是否为回收站操作
		$isRecycle = IReq::get('recycle');

		if(!empty($id))
		{
			$obj   = new IModel('right');
			$where = Util::joinStr($id);

			if($isRecycle === null)
			{
				$obj->del($where);
				$this->redirect('right_recycle');
			}
			else
			{
				//回收站操作类型
				$is_del    = ($isRecycle == 'del') ? 1 : 0;
				$obj->setData(array('is_del' => $is_del));
				$obj->update($where);
				$this->redirect('right_list');
			}
		}
		else
		{
			if($isRecycle == 'del')
				$this->redirect('right_list',false);
			else
				$this->redirect('right_recycle',false);

			Util::showMessage('请选择要操作的权限ID');
		}
	}

	//清理缓存
	function clearCache()
	{
		$runtimePath = IWeb::$app->getBasePath().'runtime';
		$result      = IFile::clearDir($runtimePath);

		if($result == true)
			echo 1;
		else
			echo -1;
	}

	//启用主题
	function applyTheme()
	{
		$issetConfig = IWeb::$app->config['theme'];
		foreach(IClient::supportClient() as $key => $client)
		{
			$clientTheme = IReq::get($client);
			if($clientTheme)
			{
				$clientData = JSON::decode($clientTheme);

				//配置文件中是否已经存在
				if(isset( $issetConfig[$client] ))
				{
					//此次启用的主题类型
					$themeType = themeroute::themeType( key($clientData) );
					if(!$themeType)
					{
						die($clientTheme."无法识别主题类型");
					}

					foreach($issetConfig[$client] as $theme => $skin)
					{
						$issetThemeType = themeroute::themeType($theme);
						if(!$issetThemeType || $themeType == $issetThemeType)
						{
							unset($issetConfig[$client][$theme]);
						}
					}
				}
				else
				{
					$issetConfig[$client] = array();
				}
				$issetConfig[$client] = array_merge($issetConfig[$client],$clientData);
			}
		}

		if($issetConfig != IWeb::$app->config['theme'])
		{
			$message = "修改成功";
			IWeb::$app->config['theme'] = $issetConfig;
			Config::edit('config/config.php',array("theme" => IWeb::$app->config['theme']));
		}

		$this->redirect('conf_ui',false);
		Util::showMessage($message);
	}

	//管理员添加快速导航
	function navigation_edit()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$navigationObj = new IModel('quick_naviga');
			$where = 'id = '.$id;
			$this->navigationRow = $navigationObj->getObj($where);
		}
		$this->redirect('navigation_edit');
	}
	//保存管理员添加快速导航
	function navigation_update()
	{
		$id = IFilter::act(IReq::get('id','post'),'int');
		$navigationObj = new IModel('quick_naviga');
		$navigationObj->setData(array(
			'admin_id'=>$this->admin['admin_id'],
			'naviga_name'=>IFilter::act(IReq::get('naviga_name')),
			'url'=>IFilter::act(IReq::get('url')),
		));
		if($id)
		{
			$navigationObj->update('id='.$id);
		}
		else
		{
			$navigationObj->add();
		}
		$this->redirect('navigation');
	}
	/**
	 * @brief 删除管理员快速导航到回收站
	 */
	function navigation_del()
	{
		$ad_id = $this->admin['admin_id'];
		$data['ad_id'] = $ad_id;
		$this->setRenderData($data);
		//post数据
    	$id = IFilter::act(IReq::get('id'),'int');
    	//生成order对象
    	$tb_order = new IModel('quick_naviga');
    	$tb_order->setData(array('is_del'=>1));
    	if(!empty($id))
		{
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$tb_order->update($where);
			$this->redirect('navigation');
		}
		else
		{
			$this->redirect('navigation',false);
			Util::showMessage('请选择要删除的数据');
		}
	}
	//管理员快速导航_回收站
	function navigation_recycle()
	{
		$data = array();
		$ad_id = $this->admin['admin_id'];
		$data['ad_id'] = $ad_id;
		$this->setRenderData($data);
		$this->redirect('navigation_recycle');
	}
	//彻底删除快速导航
	function navigation_recycle_del()
    {
    	$ad_id = $this->admin['admin_id'];
		$data['ad_id'] = $ad_id;
		$this->setRenderData($data);
    	//post数据
    	$id = IFilter::act(IReq::get('id'),'int');
    	//生成order对象
    	$tb_order = new IModel('quick_naviga');
    	if(!empty($id))
		{
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$tb_order->del($where);
			$this->redirect('navigation_recycle');
		}
		else
		{
			$this->redirect('navigation_recycle',false);
			Util::showMessage('请选择要删除的数据');
		}
    }
    //恢复快速导航
	 function navigation_recycle_restore()
    {
    	$ad_id = $this->admin['admin_id'];
		$data['ad_id'] = $ad_id;
		$this->setRenderData($data);
    	//post数据
    	$id = IFilter::act(IReq::get('id'),'int');
    	//生成order对象
    	$tb_order = new IModel('quick_naviga');
    	$tb_order->setData(array('is_del'=>0));
    	if(!empty($id))
		{
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$tb_order->update($where);
			$this->redirect('navigation_recycle');
		}
		else
		{
			$this->redirect('navigation_recycle',false);
			Util::showMessage('请选择要还原的数据');
		}
    }
    /**
     * 添加物流公司
     * */
    public function freight_edit()
    {
    	$id   = IFilter::act(IReq::get('id'),'int');
    	$data = array();
    	if($id)
    	{
    		$tb_freight = new IModel('freight_company');
    		$data = $tb_freight->getObj('id='.$id);
    	}

    	$this->data = $data;
    	$this->redirect('freight_edit');
    }
    /**
     * 保存添加或修改的物流公司
     * */
    public function freight_update()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
    	$freight_type = IReq::get('freight_type');
    	$freight_name = IReq::get('freight_name');
    	$url = IReq::get('url');
    	$sort = IReq::get('sort');

    	$tb_freight = new IModel('freight_company');
    	$tb_freight->setData(array(
    		'freight_type' => $freight_type,
    		'freight_name' => $freight_name,
    		'url'		   => $url,
    		'sort'		   => $sort
    	));

    	if($id)
    	{
    		$tb_freight->update('id='.$id);
    	}
    	else
    	{
    		$tb_freight->add();
    	}
    	$this->redirect('freight_list');
    }
    /**
     * 逻辑删除物流公司
     * */
	function freight_del()
    {
    	$id = IReq::get('id');
		if(!empty($id))
		{
			$obj = new IModel('freight_company');
			$obj->setData(array('is_del'=>1));
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$obj->update($where);
			$this->redirect('freight_list');
		}
		else
		{
			$this->redirect('freight_list',false);
			Util::showMessage('请选择要删除的物流公司');
		}
    }
	/**
     * 物流公司回收站还原
     * */
    public function freight_recycle_restore()
    {
    	$id = IReq::get('id');
		if(!empty($id))
		{
			$obj = new IModel('freight_company');
			$obj->setData(array('is_del'=>0));
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$obj->update($where);
			$this->redirect('freight_recycle');
		}
		else
		{
			$this->redirect('freight_recycle',false);
			Util::showMessage('请选择要还原的物流公司');
		}
    }
	/**
     * 物流公司回收站彻底删除
     * */
    public function freight_recycle_del()
    {
    	$id = IReq::get('id');
		if(!empty($id))
		{
			$obj = new IModel('freight_company');
			$obj->setData(array('is_del'=>0));
			if(is_array($id) && isset($id[0]) && $id[0]!='')
			{
				$id_str = join(',',$id);
				$where = ' id in ('.$id_str.')';
			}
			else
			{
				$where = 'id = '.$id;
			}
			$obj->del($where);
			$this->redirect('freight_recycle');
		}
		else
		{
			$this->redirect('freight_recycle',false);
			Util::showMessage('请选择要删除的物流公司');
		}
    }
    //修改oauth单页
    public function oauth_edit()
    {
    	$id = IFilter::act(IReq::get('id'));
    	if($id == 0)
    	{
    		$this->redirect('oauth_list',false);
    		Util::showMessage('请选择要修改的登录平台');exit;
    	}

    	$oauthDBObj = new IModel('oauth');
		$oauthRow = $oauthDBObj->getObj('id = '.$id);
		if(empty($oauthRow))
		{
    		$this->redirect('oauth_list',false);
    		Util::showMessage('请选择要修改的登录平台');exit;
		}

		//获取字段数据
		$oauthObj           = new Oauth($id);
		$oauthRow['fields'] = $oauthObj->getFields();

		$this->oauthRow = $oauthRow;
		$this->redirect('oauth_edit',false);
    }

    //修改oauth动作
    public function oauth_edit_act()
    {
    	$id = IFilter::act(IReq::get('id'));
    	if($id == 0)
    	{
    		$this->redirect('oauth_list',false);
    		Util::showMessage('请选择要修改的登录平台');exit;
    	}

    	$oauthDBObj = new IModel('oauth');
		$oauthRow = $oauthDBObj->getObj('id = '.$id);
		if(empty($oauthRow))
		{
    		$this->redirect('oauth_list',false);
    		Util::showMessage('请选择要修改的登录平台');exit;
		}

		$dataArray = array(
			'name'        => IFilter::act(IReq::get('name')),
			'is_close'    => IFilter::act(IReq::get('is_close')),
			'description' => IFilter::act(IReq::get('description')),
			'config'      => array(),
		);

		//获取字段数据
		$oauthObj    = new Oauth($id);
		$oauthFields = $oauthObj->getFields();

		if(!empty($oauthFields))
		{
			$parmsArray = array_keys($oauthFields);
			foreach($parmsArray as $val)
			{
				$dataArray['config'][$val] = IFilter::act(IReq::get($val));
			}
		}

		$dataArray['config'] = serialize($dataArray['config']);
		$oauthDBObj->setData($dataArray);
		$oauthDBObj->update('id = '.$id);
		$this->redirect('oauth_list');
    }

    /**
     * @brief 地域更新
     */
    public function area_update()
    {
    	$area_id   = IFilter::act(IReq::get('area_id'));
    	$area_name = IFilter::act(IReq::get('area_name'));
    	$area_sort = IFilter::act(IReq::get('area_sort'));
    	$parent_id = IFilter::act(IReq::get('parent_id'));

		$areasDB = new IModel('areas');

    	//添加
    	if($parent_id !== '')
    	{
    		$addData = array('parent_id' => $parent_id,'area_name' => $area_name,'sort' => 99);
    		$areasDB->setData($addData);
    		$area_id = $areasDB->add();
    		$addData['area_id'] = $area_id;
    		die(JSON::encode(array('isSuccess' => true,'data' => $addData)));
    	}
    	//修改
    	else
    	{
    		$updateData = array();
    		if($area_name)
    		{
    			$updateData['area_name'] = $area_name;
    		}

    		if($area_sort)
    		{
    			$updateData['sort'] = $area_sort;
    		}
    		$areasDB->setData($updateData);
    		$areasDB->update('area_id = '.$area_id);
    	}
    }

	/**
	 * @brief 地域删除
	 */
    public function area_del()
    {
    	$area_id = IFilter::act(IReq::get('id'),'int');
    	$areasDB = new IModel('areas');
    	$areasDB->del('area_id = '.$area_id);
    }


    /**
	 * 自提点添加和修改
	 */
	public function takeself_update()
	{
		$id       = IFilter::act(IReq::get('id'),'int');
    	$name     = IFilter::act(IReq::get('name'));
    	$sort     = IFilter::act(IReq::get('sort'),'int');
    	$province = IFilter::act(IReq::get('province'),'int');
    	$city     = IFilter::act(IReq::get('city'),'int');
    	$area     = IFilter::act(IReq::get('area'),'int');
    	$address  = IFilter::act(IReq::get('address'));
		$phone    = IFilter::act(IReq::get('phone'));
		$mobile   = IFilter::act(IReq::get('mobile'));

		$takeselfDB = new IModel('takeself');
	    $data = array(
        	'name'         => $name,
        	'sort'         => $sort,
        	'province'     => $province,
        	'city'         => $city,
        	'area'         => $area,
        	'address'      => $address,
        	'phone'        => $phone,
        	'mobile'       => $mobile,
        );
        $takeselfDB->setData($data);
        if($id)
    	{
    		$takeselfDB->update('id='.$id);
    	}
    	else
    	{
    		$takeselfDB->add();
    	}

	    $this->redirect("takeself_list");
	}


     /**
	 * 自提点添加和修改视图
	 */
	public function takeself_edit()
	{
	    $id = IFilter::act(IReq::get('id'),'int');
	    if($id)
	    {
			$takeselfDB        = new IModel('takeself');
			$this->takeselfRow = $takeselfDB->getObj('id = '.$id );
        }
		$this->redirect("takeself_edit");
	}

    //删除自提点
	public function takeself_operate()
	{
		$id = IFilter::act(IReq::get('id'));
        if(is_array($id))
        {
        	$id = join(',',$id);
        }

        if($id)
        {
			$takeself =  new IModel('takeself');
			$takeself->del('id in('.$id.')');
			$this->redirect('takeself_list');
        }
        else
        {
        	$this->redirect('takeself_list',false);
        	Util::showMessage('请选择要操作的选项');
        }
	}

	/**
	 *修改管理员密码
	 */
	function admin_repwd_act()
	{
		//提取密码 [ 密码设置 ]
		$password   = IReq::get('password','post');
		$repassword = IReq::get('repassword','post');

		if($password && $password === $repassword)
		{
			$passwordMd5 = md5($password);
			$adminObj = new IModel('admin');
			$adminObj->setData(array('password' => $passwordMd5));
			$adminObj->update('id = '.$this->admin['admin_id']);

			//同步更新safe
			ISafe::set('admin_pwd',$passwordMd5);

			$this->redirect('default');
		}
		else
		{
			$message = '密码不能为空,并且二次输入的必须一致';
			$this->redirect('admin_repwd',false);
			Util::showMessage($message);
		}
	}

	//获取微信菜单数据
	function wechat_getmenu()
	{
		$json   = wechat_facade::getMenu();
		if($json)
		{
			$result = array('result' => 'success','data' => JSON::encode($json));
		}
		else
		{
			$result = array('result' => 'fail','msg' => '获取菜单失败');
		}
		die(JSON::encode($result));
	}

	//保存微信菜单
	function wechat_setmenu()
	{
		$menuData = trim(IReq::get('menuData'));
		$array    = wechat_facade::setMenu($menuData);
		$result   = array('result' => 'success');

		if($array['errcode'] != 0)
		{
			$result = array('result' => 'fail','msg' => $array['errmsg']);
		}
		die(JSON::encode($result));
	}

	// 短信发送测试
	function test_sendhsms()
	{
		$siteConfig = new Config('site_config');
		if( !$siteConfig->sms_username || !$siteConfig->sms_pwd )
		{
			die( JSON::encode(array('isError' => true,'message' => '请先<保存>短信配置')) );
		}

		$mobile = IFilter::act(IReq::get('mobile'));
		if($mobile === null || !IValidate::mobi($mobile))
		{
			die( JSON::encode(array('isError' => true,'message' => '请输入正确的手机号码')) );
		}

		$mobile_code = rand(10000,99999);
		$content     = smsTemplate::checkCode(array('{mobile_code}' => $mobile_code));
		$send_result = Hsms::send($mobile,$content);
		if($send_result == 'success')
		{
			$result = array('isError' => false,'message' => '恭喜你！测试通过');
		}
		else
		{
			$result = array('isError' => true,'message' => $send_result);
		}
		echo JSON::encode($result);
	}
}
