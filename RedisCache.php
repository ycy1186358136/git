<?php
/**
 * Created by PhpStorm.
 * User: PC
 * Date: 2019/12/30
 * Time: 17:39
 */

class RedisCache
{
    public $key = "test:rrr";
    /**
     * @var \Redis
     */
    public $redis;

    public function connect()
    {
        $this->redis = new \Redis();
        $host        = "127.0.0.1";
        return $this->redis->connect($host);
    }


    public function push()
    {
        $this->redis->lPush($this->key, "111");
    }

    public function get()
    {
        while (1) {
            try {
                $info = $this->redis->blPop($this->key, 0);
                var_dump($info);
            } catch (\RedisException $e) {
                $this->checkout();
            } catch (\Exception $e) {

            }
        }
    }


    public function checkout()
    {
        $flag = false;
        while (1) {
            try {
                if ($this->redis->ping() === "+PONG") {
                    $flag = true;
                }
            } catch (\RedisException $e) {
                $con = $this->connect();
                if ($con) {
                    $flag = true;
                }
            }
            if ($flag) {
                break;
            }
            sleep(5);
        }
    }
}