<?php
/**
 * Created by PhpStorm.
 * User: PC
 * Date: 2019/12/28
 * Time: 14:30
 */

return [
    "db"      => [
        "host"     => "192.168.33.1",
        "port"     => "5672",
        "login"    => "admin",
        "password" => "admin",
        "vhost"    => "/",
    ],
    "channel" => [
        "test1" => [
            "exchange_name" => "exchange_test1",
            "exchange_type" => AMQP_EX_TYPE_FANOUT,
            "queue_name"    => "queue_test1",
        ],
        "test2" => [
            "exchange_name"       => "exchange_test2",
            "exchange_type"       => AMQP_EX_TYPE_DIRECT,
            "queue_name"          => "queue_test2",
            "is_open_dead_letter" => true,//是否开启死信队列  true 开启  false 不开启
            "dead_letter_mark"    => "test3",//当死信队列标识为true时候，此值必须
            "dead_letter_time"    => 5000,//消息发送延迟时间
        ],
        "test3" => [
            "exchange_name" => "exchange_test3",
            "exchange_type" => AMQP_EX_TYPE_DIRECT,
            "queue_name"    => "queue_test3",
            "routing_key"   => "queue_test3_route",
            "permission"    => 3
        ],
        "test4" => [
            "exchange_name" => "exchange_test4",
            "exchange_type" => AMQP_EX_TYPE_DIRECT,
            "queue_name"    => "queue_test4",
            "routing_key"   => "queue_test4_route",
            "is_open_later" => true,
            "later_time"    => 10000,
            "permission"    => 3//1-只创建交换机  2-只创建队列  3-即创建交换机又创建队列
        ],


    ]
];