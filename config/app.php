<?php

return [
    //是否开启谷歌令牌
    'open_google_safe' => false,
    //是否开启多国家手机号
    'open_country_phone' => false,
    //地址调试
    'url_route_must' => false,
    // 应用调试模式
    'app_debug' => true,
    // 应用Trace调试
    'app_trace' => false,
    // 0按名称成对解析 1按顺序解析
    'url_param_type' => 1,
    // 当前 ThinkAdmin 版本号
    'thinkadmin_ver' => 'v5',
    
    'default_lang' => 'ar,de,en,es,fr,ina,ind,jp,kor,pt,rus,tur,sk,it,da',
    //土耳其TUR  菲律宾PHL  澳大利亚AUS  印度INR  巴西BRA  墨西哥MEX  哥伦比亚COL  南非ZAF
    'default_country' => 'MEX',
    'lang_switch_on' => true,
    'empty_controller' => 'Error',
    'empty_module' => 'index',
    'deny_module_list' => ['lang'],
    'pwd_str' => '!qws6F!xffD2vx80?95jt',  //盐
    'default_timezone' => 'Europe/Istanbul',//时区设置
    //是否启用代理客服， 如果启用那么每个代理都需要设置自己的客服连接
    'open_agent_chat' => 1,
    //是否启用ip唯一， 如果启用那么每个ip只能注册一个用户
    'reg_ip'=>'0',//0关闭1开启
    //货币符号
    'currency'=>'',
    'recharge_money_list'=>'',
    'first_deposit_upgrade_level'=>'', //首次提现后升级到指定级别
    'clean_recharge_hour'=>'',//自动清理未支付订单
    'lang_tel_pix'=>'',
    'enable_lxb'=>'0',//是否启用利息宝
    'is_same_yesterday_order'=>'1',//是否允许做和昨天相同级别任务
    'ip_register_number'=>'0',//同一个IP注册账号数量

    'pwd_error_num' => 10,    //密码连续错误次数

    'allow_login_min' => 5,     //密码连续错误达到次数后的冷却时间，分钟

    'default_filter' => 'trim',

    'zhangjun_sms' => [
        'userid' => '????',
        'account' => '?????',
        'pwd' => '????',
        'content' => '【????】您的验证码为：',
        'min' => 5,  //短信有效时间，分钟
    ],
    //短信宝
    'smsbao' => [
        'user'=>'', //账号  无需md5
        'pass'=>'', //密码
        'sign'=>'', //签名
    ],


    //提现配置
    'payout_wallet'=>'',
    'payout_bank'=>'',
    'payout_usdt'=>'',
    'deposit_num'=>'30',
    'fees'=>'1.5',

    //bi支付
    'bipay' => [
        'appKey' => '',
        'appSecret' => '',
    ],
    //paysapi支付
    'paysapi' => [
        'uid' => '',   //bi支付 商户appkey
        'token' => '', //密钥
        'istype' => 1, //默认支付方式  1 支付宝  2 微信  3 比特币
    ],

    'app_only' => 0,            //只允许app访问
    'vip_sj_bu' => 1,            //vip升级 是否补交
    'app_url'=>'',          //app下载地址
    'version'=>'',        //版本号

    'free_balance'=>'', //账户体验金。需要在第一次充值对时候扣掉。
    'free_balance_time'=>'',
    'invite_one_money'=>'0', //邀请一个用户得到多少钱
    'invite_recharge_money'=>'', //邀请一个用户首次充值得到多少钱 5%
    'verify' => true,
    'mix_time' => '5',                    //匹配订单最小延迟
    'max_time' => '10',                   //匹配订单最大延迟
    'min_recharge' => '68',              //最小充值金额
    'max_recharge' => '50000',             //最大充值金额
    'deal_min_balance'=>'0',          //交易所需最小余额
    'deal_min_num'=>'0',               //匹配区间
    'deal_max_num'=>'100',               //匹配区间
    'deal_count'=>'0',                 //当日交易次数限制
    'deal_reward_count'=>'0',          //推荐新用户获得额外的交易次数
    'deal_timeout'=>'0',              //订单超时时间
    'deal_feedze'=>'0',              //交易冻结时长
    'deal_error'=>'0',                  //允许违规操作次数
    'vip_1_commission'=>'',          //交易佣金
    'min_deposit' => '100',               //最低提现额度
    '1_reward' => '0',                  //充值 - 1代返佣
    '2_reward' => '0',                  //充值 - 2代返佣
    '3_reward' => '0',                  //充值 - 3代返佣
    '1_d_reward' => '0',               //上级会员获得交易奖励
    '2_d_reward' => '0',               //上二级会员获得交易奖励
    '3_d_reward' => '0',               //上三级会员获得交易奖励
    '4_d_reward' => '0',               //上四级会员获得交易奖励
    '5_d_reward' => '0',                  //上五级会员获得交易奖励
    'master_cardnum'=>'TCy57P8avLGeN43AiadfK5HMETXefAgdbX',             //银行卡号
    'master_name'=>'',                              //收款人
    'master_bank'=>'',                          //所属银行
    'master_bk_address'=>'',         //银行地址
    'deal_zhuji_time'=>'',         //远程主机分配时间
    'deal_shop_time'=>'',          //等待商家响应时间
    'tixian_time_1'=>'0',           //提现开始时间
    'tixian_time_2'=>'24',          //提现结束时间

    'chongzhi_time_1'=>'0',           //充值开始时间
    'chongzhi_time_2'=>'24',          //充值结束时间


    'order_time_1'=>'0',           //抢单结束时间
    'order_time_2'=>'24',          //抢单结束时间

    //利息宝
    'lxb_bili'=>'',         //利息宝 日利率
    'lxb_time'=>'',             //利息宝 转出到余额  实际 /小时
    'lxb_sy_bili1'=>'',         //利息宝 上一级会员收益比例
    'lxb_sy_bili2'=>'',         //利息宝 上一级会员收益比例
    'lxb_sy_bili3'=>'',         //利息宝 上一级会员收益比例
    'lxb_sy_bili4'=>'',         //利息宝 上一级会员收益比例
    'lxb_sy_bili5'=>'',         //利息宝 上一级会员收益比例
    'lxb_ru_max'=>'',         //利息宝 转入最大金额
    'lxb_ru_min'=>'',         //利息宝 转入最低金额


    'shop_status'=>'',         //商城状态',
];
