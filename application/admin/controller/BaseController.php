<?php

namespace app\admin\controller;

class BaseController extends \think\Controller
{
	protected $user_info = null;
	protected $token = null;

	// 构造方法
	// is_login 用户是否需要登录，继承该控制器的，默认需要
	public function __construct($is_login = true)
	{
		parent::__construct();

		if($is_login) {
			$result = $this->checkLogin();
			if($result['code'] != 200) {
				$result['code'] = 100;
				$this->ajaxReturn($result);
			}
		}
// \think\facade\Session::boot();
// writeLog(['session_id' => session_id(), 'token' => $this->token, 'url' => time(), 'user_info' => $this->user_info], 'session');

	}



	// 判断用户是否登录
	protected function checkLogin()
	{
		// 用户是否登录
		$this->token = \think\facade\Session::get('user_token');
		if(empty($this->token)) {
			// session已经过期
			return returnData(300,'请先登录.');
		}

		// 查找redis中是否存在指定token
		$key = config('program.redis_pre') . 'token:' . $this->token ;
		$data = \app\common\service\redisTool::getInstance()->get($key);

		if(empty($data)) {
			// 通过refresh_token刷新
			$refresh_token = \think\facade\Session::get('refresh_token');

			if($refresh_token) {
				$loginModel = new \app\common\service\tencent_api\WeChatLogin();
				$return_data = $loginModel->refreshTokenLogin($refresh_token,1);
				if($return_data['code'] == 200) {
					\think\facade\Session::clear();
					\think\facade\Session::regenerate(true);
					// 保存到session
					\think\facade\Session::set('user_token',$return_data['data']['dataObj']['token']);
					\think\facade\Session::set('refresh_token',$return_data['data']['dataObj']['refresh_token']);

					$key = config('program.redis_pre') . 'token:' . $return_data['data']['dataObj']['token'];
					$data = \app\common\service\redisTool::getInstance()->get($key);
				}
			}
		}


		if($data) {
			$this->user_info = json_decode($data,true);
			$key = config('program.redis_pre') . 'refresh_token:' . $this->user_info['refresh_token'];
			$this->user_info['now_role'] = \app\common\service\redisTool::getInstance()->hGet($key, 'now_role');
			return returnData(200,'用户已登录');

		} else {
			\think\facade\Session::clear();
			\think\facade\Session::regenerate(true);
			return returnData(300,'请先登录');
		}
	}


	

	// 登录后的页面，公共返回方法
	protected function ajaxReturn($data)
	{
		header('Content-Type:application/json');
		exit(json_encode($data));
	}
}