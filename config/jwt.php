<?php
//jwt配置
return [
    'secrect' => 'shuadan',
    'iss' => 'shuadan', //签发者 可选
    'aud' => 'shuadan', //接收该JWT的一方，可选
    'exptime' => 7200, //过期时间,这里设置2个小时
];
