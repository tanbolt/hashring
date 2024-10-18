<?php
namespace Tanbolt\Hashring;

/**
 * Class Hashring: 一致性 Hash 分配算法
 * @package Tanbolt\Hashring
 */
class Hashring implements HashringInterface
{
    // pecl memcache  余数分布法
    const DISTRIBUTION_STANDARD = 0;

    // pecl memcache  一致性hash算法
    const DISTRIBUTION_CONSISTENT = 1;

    // pecl memcached 余数分布法
    const DISTRIBUTION_MODULA = 2;

    // pecl memcached 一致性hash算法
    const DISTRIBUTION_KETAMA = 3;

    // 兼容模式, 可以与 python 等其他语言客户端使用相同分布算法
    const DISTRIBUTION_COMPATIBLE = 4;

    // 可用 hash算法
    const HASH_DEFAULT = 0;
    const HASH_MD5 = 1;
    const HASH_CRC = 2;
    const HASH_FNV1_64 = 3;
    const HASH_FNV1A_64 = 4;
    const HASH_FNV1_32 = 5;
    const HASH_FNV1A_32 = 6;
    const HASH_HSIEH = 7;
    const HASH_MURMUR = 8;

    /**
     * php 支持的 hash 函数 (用于判断是否支持 fnv 算法, php5.4 以后才内置了 fnv 算法)
     * @var null
     */
    private static $hashSupport = null;

    /**
     * 虚拟节点数 (在使用 DISTRIBUTION_CONSISTENT 算法时使用)
     * @var int
     */
    private static $consistentPoint = 160;

    /**
     * hash环节点数, 2 的幂数 (在使用 DISTRIBUTION_CONSISTENT 算法时使用)
     * @var int
     */
    private static $consistentBuckets = 1024;

    /**
     * 分布算法
     * @var int
     */
    private $distribution;

    /**
     * hash算法
     * @var int
     */
    private $hash;

    /**
     * server(cache节点) => weight(权重)
     * @var array { server => weight, ... }
     */
    private $servers = [];

    /**
     * cache 节点计数器
     * @var int
     */
    private $serverCount = 0;

    /**
     * 虚拟节点计数器
     * @var int
     */
    private $bucketCount = 0;

    /**
     * key(序号) => server(cache节点)
     * @var array { key => server, ... }
     */
    private $buckets = [];

    /**
     * servers 处理结果 (在使用 DISTRIBUTION_KETAMA 算法时使用)
     * @var array
     */
    private $ketama = [];

    /**
     * 虚拟节点是否已经处理
     * @var bool
     */
    private $bucketPopulated = false;

    /**
     * 故障节点
     * @var array { server => 1, ... }
     */
    private $failServers = [];

    /**
     * 当前指定的 key
     * @var null|string
     */
    private $currentKey = null;

    /**
     * 当前分配的 server
     * @var null|string
     */
    private $currentServer = null;

    /**
     * next() 尝试次数
     * @var int
     */
    private $currentTry = 0;

    /**
     * php 原生内置支持的 hash 算法
     * @return array|null
     */
    public static function hashNativeSupport()
    {
        if (static::$hashSupport === null) {
            static::$hashSupport = hash_algos();
        }
        return static::$hashSupport;
    }

    /**
     * one-at-a-time hash 算法
     * @param string $str
     * @return string
     */
    public static function oneAtaTime(string $str)
    {
        $hash = 0;
        foreach (str_split($str) as $byte) {
            $hash += ord($byte);
            $hash += $hash << 10;
            $hash = static::to32bit($hash);
            $hash ^= static::shiftRight($hash, 6);
        }
        $hash += ($hash << 3);
        $hash = static::to32bit($hash);
        $hash ^= static::shiftRight($hash, 11);
        $hash += ($hash << 15);
        return sprintf("%u", static::to32bit($hash));
    }

    /**
     * crc32  hash 算法
     * @param string $str
     * @param bool $unsigned
     * @return float
     */
    public static function crc32(string $str, bool $unsigned = false)
    {
        $hash = crc32($str);
        // 使用 unsigned 兼容 pecl memcache consistent crc
        return $unsigned ? sprintf('%u', $hash) : (sprintf('%d', $hash) >> 16) & 0x7fff;
    }

    /**
     * md5 hash 算法
     * @param string $str
     * @return float
     */
    public static function md5(string $str)
    {
        $hash = md5($str);
        $hashes = str_split(substr($hash,0,8), 2);
        $hash = $hashes[3] . $hashes[2] . $hashes[1] . $hashes[0];
        return (float) base_convert($hash, 16, 10);
    }

    /**
     * fnv132 hash 算法
     * @param string $str
     * @return float
     */
    public static function fnv132(string $str)
    {
        // fnv 函数在 php5.4 之后才默认支持
        if (in_array('fnv132', static::hashNativeSupport())) {
            return hexdec(hash('fnv132',$str));
        }
        $hash = 0x811c9dc5;
        foreach (str_split($str) as $byte) {
            $hash += ($hash << 1) + ($hash << 4) + ($hash << 7) + ($hash << 8) + ($hash << 24);
            $hash = static::to32bit($hash);
            $hash ^= ord($byte);
        }
        return sprintf('%u', static::to32bit($hash));
    }

    /**
     * fnv1a32 hash 算法
     * @param string $str
     * @param bool $high
     * @return float
     */
    public static function fnv1a32(string $str, bool $high = false)
    {
        if (in_array('fnv1a32', static::hashNativeSupport())) {
            $hash = hexdec(hash('fnv1a32',$str));
            // 使用 $high 兼容 pecl memcache standard fnv
            return $high ? ($hash >> 16) & 0x7fff : $hash;
        }
        $hash = 0x811c9dc5;
        foreach (str_split($str) as $byte) {
            $hash ^= ord($byte);
            $hash += ($hash << 1) + ($hash << 4) + ($hash << 7) + ($hash << 8) + ($hash << 24);
            $hash = static::to32bit($hash);
        }
        $hash = static::to32bit($hash);
        // 使用 $high 兼容 pecl memcache standard fnv
        return $high ? ($hash >> 16) & 0x7fff : sprintf('%u', $hash);
    }

    /**
     * fnv164 hash 算法(仅取低八位,保留32bit)
     * @param string $str
     * @return float
     */
    public static function fnv164(string $str)
    {
        if (in_array('fnv164', static::hashNativeSupport())) {
            return (float) base_convert(substr(hash('fnv164',$str), -8), 16, 10);
        }
        $hash = 0x84222325;
        foreach (str_split($str) as $byte) {
            $hash += ($hash << 1) + ($hash << 4) + ($hash << 5) + ($hash << 7) + ($hash << 8);
            $hash = static::to32bit($hash);
            $hash ^= ord($byte);
        }
        return sprintf('%u', static::to32bit($hash));
    }

    /**
     * fnv1a64 hash 算法 (仅取低八位,保留32bit)
     * @param string $str
     * @return float
     */
    public static function fnv1a64(string $str)
    {
        if (in_array('fnv1a64', static::hashNativeSupport())) {
            return (float)base_convert(substr(hash('fnv1a64',$str), -8), 16, 10);
        }
        $hash = 0x84222325;
        foreach (str_split($str) as $byte) {
            $hash ^= ord($byte);
            $hash += ($hash << 1) + ($hash << 4) + ($hash << 5) + ($hash << 7) + ($hash << 8);
            $hash = static::to32bit($hash);
        }
        return sprintf('%u', $hash);
    }

    /**
     * hsieh hash 算法
     * @param string $str
     * @return string
     */
    public static function hsieh(string $str)
    {
        $length = strlen($str);
        $rem = $length & 3;
        $length >>= 2;
        $hash = 0;
        for (;$length > 0; $length--) {
            $hash += static::get16bits($str);
            $tmp = (static::get16bits(substr($str, 2)) << 11) ^ $hash;
            $hash = static::to32bit($hash << 16 ^ $tmp);
            $hash += static::shiftRight($hash, 11);
            $str = substr($str, 4);
        }
        switch ($rem) {
            case 3:
                $hash += static::get16bits($str);
                $hash ^= $hash << 16;
                $hash ^= ord($str[2]) << 18;
                $hash = static::to32bit($hash);
                $hash += static::shiftRight($hash, 11);
                break;
            case 2:
                $hash += static::get16bits($str);
                $hash ^= $hash << 11;
                $hash = static::to32bit($hash);
                $hash += static::shiftRight($hash, 17);
                break;
            case 1:
                $hash += ord($str);
                $hash ^= $hash << 10;
                $hash = static::to32bit($hash);
                $hash += static::shiftRight($hash, 1);
                break;
            default:
                $hash = static::to32bit($hash);
                break;
        }
        $hash ^= $hash << 3;
        $hash = static::to32bit($hash);
        $hash += static::shiftRight($hash, 5);
        $hash ^= $hash << 4;
        $hash = static::to32bit($hash);
        $hash += static::shiftRight($hash, 17);
        $hash ^= $hash << 25;
        $hash = static::to32bit($hash);
        $hash += static::shiftRight($hash, 6);
        return sprintf('%u', static::to32bit($hash));
    }

    /**
     * 获得字符串 16bit 的数据 (用在 hsieh hash 算法中)
     * @param string $str
     * @return int
     */
    protected static function get16bits(string $str)
    {
        return ord($str[0]) + (isset($str[1]) ? (ord($str[1]) << 8) : 0);
    }

    /**
     * murmur hash 算法
     * @param string $str
     * @return string
     */
    public static function murmur(string $str)
    {
        $prime = 0x5bd1e995;
        $length = strlen($str);
        $hash = (0xdeadbeef * $length) ^ $length;
        while ($length >= 4) {
            $key = ord($str[0]) | (ord($str[1]) << 8) | (ord($str[2]) << 16) | (ord($str[3]) << 24);
            $key = static::murmurMul($key, $prime);
            $key ^= static::shiftRight($key, 24);
            $key = static::murmurMul($key, $prime);

            $hash = static::murmurMul($hash, $prime);
            $hash ^= $key;

            $str = substr($str, 4);
            $length -= 4;
        }
        for (; $length > 0; $length--) {
            if ($length > 2) {
                $hash ^= ord($str[2]) << 16;
            } elseif ($length > 1) {
                $hash ^= ord($str[1]) << 8;
            } else {
                $hash ^= ord($str[0]);
                $hash = static::murmurMul($hash, $prime);
            }
        }
        $hash ^= static::shiftRight($hash, 13);
        $hash  = static::murmurMul($hash, $prime);
        $hash ^= static::shiftRight($hash, 15);
        return sprintf('%u', $hash);
    }

    /**
     * 获得两个大数字乘积 (用在 murmur hash 算法中)
     * @param $h
     * @param $m
     * @return float
     */
    protected static function murmurMul($h, $m)
    {
        return (  (($h & 0xffff) * $m) +
                ((((($h >= 0 ? $h >> 16 : (($h & 0x7fffffff) >> 16) | 0x8000)) * $m) & 0xffff) << 16)
            ) & 0xffffffff;
    }

    /**
     * 转换数字为 32bit
     * @param $int
     * @return int
     */
    protected static function to32bit($int)
    {
        return PHP_INT_SIZE > 4 ? $int & 0xFFFFFFFF : $int;
    }

    /**
     * 向右偏移 (php 32位溢出后 向右偏移不正确)
     * @param $int
     * @param $shift
     * @return int
     */
    protected static function shiftRight($int, $shift)
    {
        if (0 <= $int = (int) $int) {
            return $int >> $shift;
        }
        return (($int & 0x7FFFFFFF) >> $shift) | (0x40000000 >> ($shift - 1));
    }

    /**
     * 实例化
     * @param int $distribution
     * @param int $hash
     * @see setOption
     */
    public function __construct(int $distribution = self::DISTRIBUTION_KETAMA, int $hash = self::HASH_DEFAULT)
    {
        $this->setOption($distribution, $hash);
    }

    /**
     * 设置对象的 分布算法 hash算法
     * @param int $distribution 分布算法
     * @param int $hash hash算法 (当分布算法为 DISTRIBUTION_COMPATIBLE $hash 参数将被忽略)
     */
    public function setOption(int $distribution, int $hash)
    {
        $trace = null;
        // 检查 是否支持指定分布算法 为简化代码 此处直接写为数字
        if ($distribution < 0 || $distribution > 4) {
            $trace = debug_backtrace();
            trigger_error('Distribution is not support in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_WARNING);
            $distribution = static::DISTRIBUTION_KETAMA;
        }
        $this->distribution = $distribution;
        // 检查 是否支持指定hash算法  为简化代码 此处直接写为数字
        if ($hash < 0 || $hash > 8) {
            $trace = null !== $trace ?: debug_backtrace();
            trigger_error('Hash Function is not support in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_WARNING);
            $hash = static::HASH_DEFAULT;
        }
        $this->hash = $hash;
    }

    /**
     * 获取指定 $key 的 hash
     * @param string $key
     * @return int
     */
    public function hash(string $key)
    {
        switch ($this->hash)
        {
            case static::HASH_CRC:
                return static::crc32($key, self::DISTRIBUTION_CONSISTENT === $this->distribution);
            case static::HASH_MD5:
                return static::md5($key);
            case static::HASH_FNV1_32:
                return static::fnv132($key);
            case static::HASH_FNV1A_32:
                return static::fnv1a32($key, self::DISTRIBUTION_STANDARD === $this->distribution);
            case static::HASH_FNV1_64:
                return static::fnv164($key);
            case static::HASH_FNV1A_64:
                return static::fnv1a64($key);
            case static::HASH_HSIEH:
                return static::hsieh($key);
            case static::HASH_MURMUR:
                return static::murmur($key);
        }
        return static::oneAtaTime($key);
    }

    /**
     * 添加 node 节点
     * > 添加一个
     *     adds('node', 1)
     *
     * > 批量添加
     *     adds
     *         [
     *              'ip:port',
     *              ['ip:port',2],
     *         ],
     *         defaultWeight
     *     );
     * @inheritDoc
     */
    public function add($nodes, int $weight = 1)
    {
        if (is_array($nodes)) {
            foreach ((array) $nodes as $node) {
                $host = null;
                if (is_array($node)) {
                    $host = array_shift($node);
                    if (null !== $host && ($tmp = array_shift($node)) !== null) {
                        $weight = $tmp;
                    }
                } elseif (is_string($node)) {
                    $host = $node;
                }
                if ($host != null) {
                    $this->add($host, $weight);
                }
            }
        } else {
            $weight = max($weight, 1);
            $this->servers[$nodes] = $weight;
            $this->serverCount++;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $node)
    {
        if (isset($this->servers[$node])) {
            unset($this->servers[$node]);
            $this->serverCount--;
            $this->bucketPopulated = false;
            if (isset($this->failServers[$node])) {
                unset($this->failServers[$node]);
            }
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function failOver(string $node)
    {
        $this->failServers[$node] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function all()
    {
        $return = [];
        foreach ($this->servers as $server => $weight) {
            $return[] = [
                'server' => $server,
                'weight' => $weight,
                'fail' => isset($this->failServers[$server]),
            ];
        }
        return $return;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key)
    {
        if ($this->serverCount < 1) {
            return null;
        }
        $this->currentTry = 0;
        $this->currentKey = $key;
        if (self::DISTRIBUTION_STANDARD === $this->distribution ||
            self::DISTRIBUTION_MODULA === $this->distribution
        ) {
            $this->currentServer = $this->standardGet($key);
        } elseif (self::DISTRIBUTION_KETAMA === $this->distribution ||
            self::DISTRIBUTION_COMPATIBLE === $this->distribution
        ) {
            $this->currentServer = $this->ketamaGet($key);
        } else {
            $this->currentServer = $this->consistentGet($key);
        }
        if (isset($this->failServers[$this->currentServer])) {
            return $this->next();
        }
        return $this->currentServer;
    }

    /**
     * 下一个合适的 cache 节点  故障转移使用
     * @return string|null
     */
    protected function next()
    {
        if ($this->currentKey === null || $this->serverCount < 1) {
            return null;
        }
        if (self::DISTRIBUTION_STANDARD === $this->distribution ||
            self::DISTRIBUTION_MODULA === $this->distribution
        ) {
            $server = $this->standardNext();
        } elseif (self::DISTRIBUTION_KETAMA === $this->distribution ||
            self::DISTRIBUTION_COMPATIBLE === $this->distribution
        ) {
            $server = $this->ketamaNext();
        } else {
            $server = $this->consistentNext();
        }
        if (isset($this->failServers[$server]) || (null !== $this->currentServer && $server === $this->currentServer)) {
            return $this->next();
        }
        $this->currentServer = $server;
        return $server;
    }

    /*   Pecl Memcache 一致性hash算法
     * ----------------------------------------------------- */
    protected function consistentGet($key)
    {
        if (!$this->bucketPopulated) {
            $this->consistentPopulate();
        }
        return $this->consistentGetByKey($key);
    }

    protected function consistentNext()
    {
        // 此处获取下一个拼凑方法是可以和余数分布法合并的, 使用不同拼凑方法是为了顺便兼容 perl memcache 2.2.7 以下版本
        $key = sprintf('%s-%d', $this->currentKey, ++$this->currentTry);
        return $this->consistentGetByKey($key);
    }

    protected function consistentGetByKey($key)
    {
        $point = intval(fmod($this->hash($key), static::$consistentBuckets));
        return $this->buckets[$point] ?? $this->buckets[0];
    }

    protected function consistentPopulate()
    {
        $this->buckets = [];
        $this->bucketCount = 0;

        // 将所有 server 按照权重整理为一个数组
        $points = [];
        $sort = [];
        foreach ($this->servers as $server => $weight) {
            $replicas = $weight * static::$consistentPoint;
            for ($i = 0; $i < $replicas; $i++) {
                $key = $this->bucketCount + $i;
                $point = $this->hash(sprintf('%s-%d', $server, $i));
                $points[$key] = [
                    'server' => $server,
                    'point' => $point,
                ];
                $sort[$key] = $point;
            }
            $this->bucketCount += $replicas;
        }
        array_multisort($sort, SORT_ASC, $points);

        // 以整理过的 server 数组为数据 分成 $this->consistentBuckets 份均分到一个闭环上
        $step =  0xFFFFFFFF / static::$consistentBuckets;
        $step = (int) $step;
        for ($i = 0; $i < static::$consistentBuckets; $i++) {
            $unit = sprintf('%u', $step * $i);
            $lo = 0;
            $hi = $this->bucketCount -1;
            while (1) {
                if ($unit <= $points[$lo]['point'] || $unit > $points[$hi]['point']) {
                    $this->buckets[$i] =  $points[$lo]['server'];
                    break;
                }
                $mid = $lo + ($hi - $lo) / 2;
                $mid = (int) $mid;
                if ($unit <= $points[$mid]['point'] && $unit > ($mid ? $points[$mid-1]['point'] : 0)) {
                    $this->buckets[$i] =  $points[$mid]['server'];
                    break;
                }
                if ($points[$mid]['point'] < $unit) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid - 1;
                }
            }
        }
        $this->bucketPopulated = true;
    }

    /*   Pecl Memcached 一致性hash算法 / COMPATIBLE 兼容算法
     * ------------------------------------------------------------------------ */
    protected function ketamaGet($key)
    {
        if (!$this->bucketPopulated) {
            $this->ketamaPopulate();
        }
        return $this->ketamaGetByKey($key);
    }

    protected function ketamaNext()
    {
        $key = sprintf('%d%s', ++$this->currentTry, $this->currentKey);
        return $this->ketamaGetByKey($key);
    }

    protected function ketamaGetByKey($key)
    {
        if (self::DISTRIBUTION_COMPATIBLE === $this->distribution) {
            $hash = static::md5($key);
        } else {
            $hash = $this->hash($key);
        }
        $left = $begin = 0;
        $right = $end = $this->bucketCount;
        while ($left < $right)
        {
            $middle= $left + floor(($right - $left) / 2);
            if ($this->buckets[$middle]['hash'] < $hash) {
                $left = $middle + 1;
            } else {
                $right = $middle;
            }
        }
        if ($right == $end) {
            $right = $begin;
        }
        if (isset($this->buckets[$right])) {
            $index = $this->buckets[$right]['index'];
        } else {
            $index = 0;
        }
        $server = $this->ketama[$index]['host'];
        if ($this->ketama[$index]['port']) {
            $server .= ':'.$this->ketama[$index]['port'];
        }
        return $server;
    }

    protected function ketamaPopulate()
    {
        $this->ketama = [];
        $totalWeight = 0;
        $tmpWeight = false;
        $useWeight = self::DISTRIBUTION_COMPATIBLE === $this->distribution;
        foreach ($this->servers as $server => $weight) {
            if (strpos($server, ':')) {
                list ($host, $port) = explode(':', $server);
            } else {
                $host = $server;
                $port = false;
            }
            $this->ketama[] = [
                'host' => $host,
                'port' => $port,
                'weight' => $weight,
            ];
            $totalWeight += $weight;
            if (!$useWeight) {
                if (false !== $tmpWeight && $tmpWeight != $weight) {
                    $useWeight = true;
                }
                $tmpWeight = $weight;
            }
        }
        $this->buckets = [];
        $this->bucketCount = 0;
        $pointServer = $useWeight ? 160 : 100;
        $pointHash = 1;
        foreach ($this->ketama as $key => $val) {
            if ($useWeight) {
                $pct = $val['weight'] / $totalWeight;
                $pointServer = floor(($pct * 160 / 4 * $this->serverCount + 0.0000000001)) * 4;
                $pointHash = 4;
            }
            for ($index = 1; $index <= $pointServer / $pointHash; $index++) {
                if ($val['port'] === false || $val['port'] == 11211) {
                    $sortHost = sprintf("%s-%u", $val['host'], $index - 1);
                } else {
                    $sortHost = sprintf("%s:%u-%u", $val['host'], $val['port'], $index - 1);
                }
                if ($useWeight) {
                    for ($x = 0; $x < $pointHash; $x++) {
                        $hash = static::ketamaMd5($sortHost, $x);
                        $this->buckets[] = [
                            'index' => $key,
                            'hash' => $hash,
                        ];
                        $sort[] = $hash;
                    }
                } else {
                    $hash = $this->hash($sortHost);
                    $this->buckets[] = [
                        'index' => $key,
                        'hash' => $hash,
                    ];
                    $sort[] = $hash;
                }
            }
            $this->bucketCount += $pointServer;
        }
        array_multisort($sort, SORT_ASC, $this->buckets);
        $this->bucketPopulated = true;
    }

    protected static function ketamaMd5($str, $k)
    {
        $hash = md5($str);
        $hashes = str_split($hash, 2);
        $hash = ((hexdec($hashes[3 + $k * 4]) & 0xFF) << 24)
            | ((hexdec($hashes[2 + $k * 4]) & 0xFF) << 16)
            | ((hexdec($hashes[1 + $k * 4]) & 0xFF) << 8)
            | (hexdec($hashes[$k * 4]) & 0xFF);
        return sprintf("%u", $hash);
    }

    /*  Pecl Memcache / Pecl Memcached 余数分布算法
     * ----------------------------------------------------- */
    protected function standardGet($key)
    {
        if (!$this->bucketPopulated) {
            $this->standardPopulate();
        }
        return $this->standardGetByKey($key);
    }

    protected function standardNext()
    {
        $key = sprintf('%d%s', ++$this->currentTry, $this->currentKey);
        return $this->standardGetByKey($key);
    }

    protected function standardGetByKey($key)
    {
        $point = intval(fmod($this->hash($key), $this->bucketCount));
        return $this->buckets[$point] ?? $this->buckets[0];
    }

    protected function standardPopulate()
    {
        $this->buckets = [];
        $this->bucketCount = 0;
        foreach ($this->servers as $server => $weight) {
            // pecl memcached 在使用余数分布法时  会忽略 weight 的设置
            // 这里为了保持一致 也忽略 weight 设置
            if (self::DISTRIBUTION_MODULA === $this->distribution) {
                $weight = 1;
            }
            for ($i = 0; $i < $weight; $i++) {
                $this->buckets[$this->bucketCount + $i] = $server;
            }
            $this->bucketCount += $weight;
        }
        $this->bucketPopulated = true;
    }
}
