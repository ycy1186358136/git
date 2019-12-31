<?php

/**
 * Created by PhpStorm.
 * User: PC
 * Date: 2019/12/28
 * Time: 14:22
 */
class RabbitmqHelper
{
    /**
     * 连接对象
     *
     * @var \AMQPConnection
     */
    public $_conn;

    /**
     * 通道对象
     *
     * @var \AMQPChannel
     */
    public $_channel;

    /**
     * 交换机对象
     *
     * @var \AMQPExchange
     */
    public $_exchange;


    /**
     * 交换机名称
     *
     * @var string
     */
    public $exchange_name = "";

    /**
     * 路由名称
     *
     * @var string
     */
    public $routing_key = "";

    /**
     * 交换机类型
     *
     * @var string
     */
    public $exchange_type = AMQP_EX_TYPE_FANOUT;

    /**
     *
     * @var array
     */
    public $arguments = [];


    /**
     * 队列对象
     *
     * @var \AMQPQueue
     */
    public $_queue;

    /**
     * 队列名称
     *
     * @var string
     */
    public $queue_name = "";


    /**
     * rabbitmq 配置
     *
     * @var array
     */
    public $dbConfigs = [];


    /**
     * 连接配置
     *
     * @var array
     */
    public $conConfig = [];

    /**
     * 通道配置
     *
     * @var array
     */
    public $channelConfig       = [];
    public $is_open_dead_letter = false;//是否开启死信队列
    public $dead_letter_mark    = "";//死信队列标识
    public $dead_letter_time    = 0;//死信队列延时时间
    public $is_open_later       = false;//是否开启延时队列
    public $later_time          = 0;//延时时间

    /*
     * 1: previously declared exchange
     * 2: previously declared queue
     */
    private $permission = 0;

    /*
     * exchange
     */
    const PERMISSION_EXCHANGE = 1;

    /*
     * queue
     */
    const PERMISSION_QUEUE = 2;

    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->dbConfigs = $config;
        }
    }

    /**
     * 设置MQ连接信息
     *
     * 创建时间:2019/12/28
     *
     * @param array $config
     *
     */
    public function setConfig(array $config)
    {
        $this->dbConfigs = $config;
    }

    /**
     *  设置mq的交换机以及队列配置信息
     *
     * 创建时间:2019/12/28
     *
     * @param string $flage
     *
     * @throws  \Exception
     */
    public function setExchangeQueueConfig($flage)
    {
        if (empty($this->dbConfigs['db'])) {
            throw new \Exception("连接配置信息不存在");
        }
        if (empty($this->dbConfigs['channel'][$flage])) {
            throw new \Exception("配置标识不存在请检查");
        }
        $this->conConfig = $this->dbConfigs["db"];
        $this->checkConfig($this->conConfig);
        $exConfig = $this->dbConfigs['channel'][$flage];
        foreach ($exConfig as $k => $v) {
            $this->$k = $v;
        }
    }


    /**
     * 创建交换机以及队列
     *
     * 创建时间:2019/12/28
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function init()
    {
        $this->setConnect();
        $this->setChannel();
        $this->setExchange();
        $this->setQueue();
    }


    /**
     * 验证MQ参数
     *
     * 创建时间:2019/12/28
     */

    public function checkConfig($conConfig)
    {
        if (!$conConfig['host']) {
            throw new \Exception("请配置地址");
        }
        if (!$conConfig['port']) {
            throw new \Exception("请配置端口");
        }
        if (!$conConfig['vhost']) {
            throw new \Exception("请配置域");
        }
        if (!$conConfig['password']) {
            throw new \Exception("请配置密码");
        }
        if (!$conConfig['login']) {
            throw new \Exception("请配置用户名");
        }
    }

    /**
     * 连接MQ
     *
     * 创建时间:2019/12/31
     *
     * @throws AMQPConnectionException
     */
    public function setConnect()
    {
        $this->_conn = new \AMQPConnection($this->conConfig);
        $this->_conn->connect();
    }

    /**
     * 获取连接
     *
     * 创建时间:2019/12/31
     *
     * @return AMQPConnection
     */
    public function getConnect()
    {
        return $this->_conn;
    }

    /**
     * 创建信道
     *
     * 创建时间:2019/12/28
     *
     * @throws \AMQPConnectionException
     */
    public function setChannel()
    {
        $this->_channel = new \AMQPChannel($this->_conn);
    }

    /**
     * 创建交换机 和死信交换机
     *
     * 创建时间:2019/12/28
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function setExchange()
    {
        if ($this->permission & self::PERMISSION_EXCHANGE) {
            $this->_exchange = new \AMQPExchange($this->_channel);
            $this->_exchange->setName($this->exchange_name);
            if ($this->is_open_later) {
                $this->_exchange->setType("x-delayed-message");
                $this->_exchange->setArgument('x-delayed-type', $this->exchange_type);
            } else {
                $this->_exchange->setType($this->exchange_type);
            }
            $this->_exchange->setFlags(AMQP_DURABLE);
            if ($this->is_open_dead_letter && !empty($this->dead_letter_mark)) {//存在死信队列
                if (!empty($this->dbConfigs['channel'][$this->dead_letter_mark])) {
                    $ex_name = $this->dbConfigs['channel'][$this->dead_letter_mark]['exchange_name'];
                    $ex_type = $this->dbConfigs['channel'][$this->dead_letter_mark]['exchange_type'];
                    $ex      = new \AMQPExchange($this->_channel);
                    $ex->setName($ex_name);
                    $ex->setType($ex_type);
                    $ex->setFlags(AMQP_DURABLE);
                    $ex->declareExchange();
                }
            }
            $this->_exchange->declareExchange();
        }
    }

    /**
     * 创建队列
     *
     * 创建时间:2019/12/28
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPQueueException
     */
    public function setQueue()
    {
        if ($this->permission & self::PERMISSION_QUEUE) {
            $this->_queue = new \AMQPQueue($this->_channel);
            $this->_queue->setName($this->queue_name);
            if ($this->exchange_type == AMQP_EX_TYPE_FANOUT) {
                $this->_queue->setFlags(AMQP_EXCLUSIVE);
            }
            $this->_queue->setFlags(AMQP_DURABLE);
            if ($this->is_open_dead_letter && !empty($this->dead_letter_mark)) {//存在死信队列
                if (!empty($this->dbConfigs['channel'][$this->dead_letter_mark])) {
                    $qu_name     = $this->dbConfigs['channel'][$this->dead_letter_mark]['queue_name'];
                    $routing_key = empty($this->dbConfigs['channel'][$this->dead_letter_mark]['routing_key']) ? "" : $this->dbConfigs['channel'][$this->dead_letter_mark]['routing_key'];
                    $ex_name     = $this->dbConfigs['channel'][$this->dead_letter_mark]['exchange_name'];
                    $que         = new \AMQPQueue($this->_channel);
                    $que->setName($qu_name);
                    $que->setFlags(AMQP_DURABLE);
                    $this->_queue->setArguments([
                        "x-dead-letter-exchange"    => "{$ex_name}",
                        "x-dead-letter-routing-key" => "{$routing_key}",
                    ]);
                    $que->declareQueue();
                    if ($this->exchange_type == AMQP_EX_TYPE_FANOUT) {
                        $que->bind($this->exchange_name, "");
                    } else {
                        $que->bind($ex_name, $routing_key);
                    }
                }
            }
            $this->_queue->declareQueue();
            if ($this->exchange_type == AMQP_EX_TYPE_FANOUT) {
                $this->_queue->bind($this->exchange_name, "");
            } else {
                $this->_queue->bind($this->exchange_name, $this->routing_key);
            }
        }
    }


    /**
     * 检测连接是否可用
     *
     * 创建时间:2019/12/28
     *
     * @return bool
     */
    private function ping()
    {
        return $this->_channel->isConnected();
    }


    /**
     * 创建时间:2019/12/30
     * @param callable $func_name
     * @return string
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function mqconsume(callable $func_name)
    {
        $this->init();
        while (true) {
            try {
                if (!$this->_queue) {
                    throw new \Exception("没有队列信息");
                }
                $this->_queue->consume($func_name);
            } catch (\AMQPException $e) {
                $this->checkConnect();
            } catch (\Exception $e) {
                var_dump($e->getMessage());
                exit;
            }
        }
    }

    /**
     * MQ断线重连
     *
     * 创建时间:2019/12/31
     */
    public function checkConnect()
    {
        while (1) {
            try {
                $this->init();
                if ($this->ping()) {
                    break;
                }
                sleep(1);
            } catch (\AMQPException $e) {

            }
        }
    }

    /**
     * 发布消息
     *
     * 创建时间:2019/12/31
     *
     * @param  string $msg 消息值
     * @param array   $params 消息参数
     *
     * $params.expiration =>延时时间
     *
     *
     * @return bool
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPExchangeException
     * @throws AMQPQueueException
     */

    public function mqSend($msg, $params = [])
    {
        $this->init();
        $attributes = [];
        if ($this->is_open_dead_letter) {
            $attributes = ["expiration" => $this->dead_letter_time];
        }
        if ($this->is_open_later) {
            if (!empty($params['expiration'])) {
                $params['headers'] = [
                    'x-delay' => $params['expiration']
                ];
                unset($params['expiration']);
            }
            $attributes = ['headers' => ['x-delay' => $this->later_time]];
        }
        $attributes = array_merge($attributes, $params);
        return $this->_exchange->publish($msg, $this->routing_key, $flags = AMQP_NOPARAM, $attributes);
    }


    public function __destruct()
    {
        $this->close();
    }

    /**
     * 关闭MQ连接
     *
     * 创建时间:2019/12/31
     */
    public function close()
    {
        if ($this->_conn) {
            $this->_conn->disconnect();
        }
    }

}