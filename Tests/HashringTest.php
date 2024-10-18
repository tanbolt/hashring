<?php

use Tanbolt\Hashring\Hashring;
use PHPUnit\Framework\TestCase;

class HashringTest extends TestCase
{
    protected $string_keys = [
        "apple",
        "beat",
        "carrot",
        "daikon",
        "eggplant",
        "flower",
        "green",
        "hide",
        "ick",
        "jack",
        "kick",
        "lime",
        "mushrooms",
        "nectarine",
        "orange",
        "peach",
        "quant",
        "ripen",
        "strawberry",
        "tang",
        "up",
        "volumne",
        "when",
        "yellow",
        "zip",
    ];

    // https://github.com/trondn/libmemcached/blob/stable/tests/hash_results.h
    public function hashDataProvider($hash)
    {
        $values['oneAtaTime'] = [
            2297466611, 3902465932, 469785835, 1937308741,
            261917617, 3785641677, 1439605128, 1649152283,
            1493851484, 1246520657, 2221159044, 1973511823,
            384136800, 214358653, 2379473940, 4269788650,
            2864377005, 2638630052, 427683330, 990491717,
            1747111141, 792127364, 2599214128, 2553037199,
            2509838425
        ];
        $values['crc32'] = [
            10542, 22009, 14526, 19510, 19432, 10199, 20634,
            9369, 11511, 10362, 7893, 31289, 11313, 9354,
            7621, 30628, 15218, 25967, 2695, 9380,
            17300, 28156, 9192, 20484, 16925
        ];
        $values['md5'] = [
            3195025439, 2556848621, 3724893440, 3332385401,
            245758794, 2550894432, 121710495, 3053817768,
            1250994555, 1862072655, 2631955953, 2951528551,
            1451250070, 2820856945, 2060845566, 3646985608,
            2138080750, 217675895, 2230934345, 1234361223,
            3968582726, 2455685270, 1293568479, 199067604,
            2042482093
        ];
        $values['fnv132'] = [
            67176023, 1190179409, 2043204404, 3221866419,
            2567703427, 3787535528, 4147287986, 3500475733,
            344481048, 3865235296, 2181839183, 119581266,
            510234242, 4248244304, 1362796839, 103389328,
            1449620010, 182962511, 3554262370, 3206747549,
            1551306158, 4127558461, 1889140833, 2774173721,
            1180552018
        ];
        $values['fnv1a32'] = [
            280767167, 2421315013, 3072375666, 855001899,
            459261019, 3521085446, 18738364, 1625305005,
            2162232970, 777243802, 3323728671, 132336572,
            3654473228, 260679466, 1169454059, 2698319462,
            1062177260, 235516991, 2218399068, 405302637,
            1128467232, 3579622413, 2138539289, 96429129,
            2877453236
        ];
        $values['fnv164'] = [
            473199127, 4148981457, 3971873300, 3257986707,
            1722477987, 2991193800, 4147007314, 3633179701,
            1805162104, 3503289120, 3395702895, 3325073042,
            2345265314, 3340346032, 2722964135, 1173398992,
            2815549194, 2562818319, 224996066, 2680194749,
            3035305390, 246890365, 2395624193, 4145193337,
            1801941682
        ];
        $values['fnv1a64'] = [
            1488911807, 2500855813, 1510099634, 1390325195,
            3647689787, 3241528582, 1669328060, 2604311949,
            734810122, 1516407546, 560948863, 1767346780,
            561034892, 4156330026, 3716417003, 3475297030,
            1518272172, 227211583, 3938128828, 126112909,
            3043416448, 3131561933, 1328739897, 2455664041,
            2272238452
        ];
        $values['hsieh'] = [
            3738850110, 3636226060, 3821074029, 3489929160, 3485772682, 80540287,
            1805464076, 1895033657, 409795758, 979934958, 3634096985, 1284445480,
            2265380744, 707972988, 353823508, 1549198350, 1327930172, 9304163,
            4220749037, 2493964934, 2777873870, 2057831732, 1510213931, 2027828987,
            3395453351
        ];
        $values['murmur'] = [
            4142305122, 734504955, 3802834688, 4076891445,
            387802650, 560515427, 3274673488, 3150339524,
            1527441970, 2728642900, 3613992239, 2938419259,
            2321988328, 1145154116, 4038540960, 2224541613,
            264013145, 3995512858, 2400956718, 2346666219,
            926327338, 442757446, 1770805201, 560483147,
            3902279934
        ];
        if (isset($values[$hash])) {
            return array_combine($this->string_keys, $values[$hash]);
        }
        static::fail('Hash Function not found');
        return [];
    }

    public function testOneAtaTime()
    {
        $data = $this->hashDataProvider('oneAtaTime');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::oneAtaTime($k), sprintf('%u', $v));
        }
    }

    public function testCrc32()
    {
        $data = $this->hashDataProvider('crc32');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::crc32($k), sprintf('%u', $v));
        }
    }

    public function testMd5()
    {
        $data = $this->hashDataProvider('md5');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::md5($k), sprintf('%u', $v));
        }
    }

    public function testFnv132()
    {
        $data = $this->hashDataProvider('fnv132');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::fnv132($k), sprintf('%u', $v));
        }
    }

    public function testFnv1a32()
    {
        $data = $this->hashDataProvider('fnv1a32');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::fnv1a32($k), sprintf('%u', $v));
        }
    }

    public function testFnv164()
    {
        $data = $this->hashDataProvider('fnv164');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::fnv164($k), sprintf('%u', $v));
        }
    }

    public function testFnv1a64()
    {
        $data = $this->hashDataProvider('fnv1a64');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::fnv1a64($k), sprintf('%u', $v));
        }
    }

    public function testHsieh()
    {
        $data = $this->hashDataProvider('hsieh');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::hsieh($k), sprintf('%u', $v));
        }
    }

    public function testMurmur()
    {
        $data = $this->hashDataProvider('murmur');
        foreach ($data as $k=>$v) {
            static::assertEquals(Hashring::murmur($k), sprintf('%u', $v));
        }
    }

    public function serverDataProvider()
    {
        return [
            [
                'host' => '127.0.0.1',
                'port' => 11211,
                'weight' => 2,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 11212,
                'weight' => 2,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 11213,
                'weight' => 1,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 11214,
                'weight' => 3,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 11215,
                'weight' => 4,
            ],
        ];
    }

    // 在 php memcache (2.2.5)   php memcached (	2.2.0)  实测结果
    public function findServerDataProvider($hash)
    {
        $values['STANDARD_CRC'] = [
            11213,11215,11212,11211,11213,11215,11215,11215,11212,
            11213,11214,11215,11214,11215,11212,11214,11214,11213,
            11211,11211,11211,11212,11213,11215,11211,
        ];
        $values['STANDARD_CRC_WEIGHT'] = [
            11214,11211,11214,11215,11213,11215,11214,11215,11212,
            11214,11215,11214,11215,11214,11211,11213,11212,11215,
            11214,11215,11215,11213,11211,11211,11214,
        ];

        $values['CONSISTENT_CRC'] = [
            11214,11215,11215,11215,11214,11215,11215,11211,11215,
            11211,11212,11215,11214,11212,11211,11215,11215,11211,
            11211,11212,11215,11211,11214,11215,11215,
        ];
        $values['CONSISTENT_CRC_WEIGHT'] = [
            11214,11212,11215,11214,11214,11215,11214,11211,11214,
            11211,11212,11215,11215,11211,11214,11215,11215,11211,
            11211,11215,11215,11214,11215,11214,11215,
        ];

        $values['MODULA_CRC'] = [
            11213,11215,11212,11211,11213,11215,11215,11215,11212,
            11213,11214,11215,11214,11215,11212,11214,11214,11213,
            11211,11211,11211,11212,11213,11215,11211,
        ];
        $values['MODULA_CRC_WEIGHT'] = [
            11213,11215,11212,11211,11213,11215,11215,11215,11212,
            11213,11214,11215,11214,11215,11212,11214,11214,11213,
            11211,11211,11211,11212,11213,11215,11211,
        ];

        $values['KETAMA_CRC'] = [
            11213,11214,11212,11211,11213,11212,11212,11211,11215,
            11212,11215,11214,11215,11211,11214,11211,11212,11211,
            11212,11211,11213,11213,11211,11212,11215,
        ];
        $values['KETAMA_CRC_WEIGHT'] = [
            // 巧了 真的就全部是 11215 服务器
            11215,11215,11215,11215,11215,11215,11215,11215,11215,
            11215,11215,11215,11215,11215,11215,11215,11215,11215,
            11215,11215,11215,11215,11215,11215,11215,
        ];
        // 多测试一个 hash 函数
        $values['KETAMA_oneAtaTime_WEIGHT'] = [
            11212,11212,11214,11215,11215,11211,11215,11215,11214,
            11214,11215,11214,11214,11215,11212,11215,11215,11211,
            11212,11215,11211,11214,11211,11215,11215,
        ];

        $values['COMPATIBLE'] = [
            11212,11214,11212,11213,11215,11214,11212,11211,11215,
            11213,11211,11213,11211,11213,11214,11214,11214,11215,
            11213,11215,11211,11215,11212,11215,11213,
        ];
        $values['COMPATIBLE_WEIGHT'] = [
            11212,11214,11213,11215,11215,11215,11212,11211,11215,
            11213,11211,11214,11215,11214,11214,11214,11214,11215,
            11215,11215,11211,11215,11212,11215,11215,
        ];

        $values['REDISSESSION'] = [
            11211,11213,11211,11211,11211,11215,11211,11215,11214,
            11211,11212,11212,11215,11214,11213,11211,11213,11213,
            11213,11214,11215,11211,11211,11212,11211
        ];

        $values['REDISSESSION_WEIGHT'] = [
            11212,11215,11213,11214,11214,11215,11211,11212,11214,
            11211,11213,11215,11215,11214,11215,11214,11215,11215,
            11215,11215,11214,11211,11211,11212,11214
        ];

        if (isset($values[$hash])) {
            return array_combine($this->string_keys, $values[$hash]);
        }
        $this->fail('Hash Function not found');
        return [];
    }

    public function testBasicMethod()
    {
        $servers = [
            [
                'server' => 'cache',
                'weight' => 1,
                'fail' => false,
            ],
            [
                'server' => 'cache2',
                'weight' => 2,
                'fail' => false,
            ]
        ];
        $hashring = new Hashring();
        static::assertSame($hashring, $hashring->add('cache', 1));
        static::assertEquals([$servers[0]], $hashring->all());
        static::assertSame($hashring, $hashring->add('cache2', 2));
        static::assertEquals($servers, $hashring->all());

        $hashring = new Hashring();
        static::assertSame($hashring, $hashring->add(['cache', ['cache2', 2]]));
        static::assertEquals($servers, $hashring->all());

        static::assertSame($hashring, $hashring->failOver('cache'));
        $servers[0]['fail'] = true;
        static::assertEquals($servers, $hashring->all());

        static::assertSame($hashring, $hashring->remove('cache'));
        static::assertEquals([$servers[1]], $hashring->all());
    }

    public function testGetServer_STANDARD_CRC()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_STANDARD, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port']);
        }
        $data = $this->findServerDataProvider('STANDARD_CRC');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_STANDARD_CRC_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_STANDARD, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('STANDARD_CRC_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_CONSISTENT_CRC()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_CONSISTENT, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port']);
        }
        $data = $this->findServerDataProvider('CONSISTENT_CRC');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_CONSISTENT_CRC_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_CONSISTENT, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('CONSISTENT_CRC_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_MODULA_CRC()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_MODULA, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port']);
        }
        $data = $this->findServerDataProvider('MODULA_CRC');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_MODULA_CRC_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_MODULA, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('MODULA_CRC_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_KETAMA_CRC()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_KETAMA, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port']);
        }
        $data = $this->findServerDataProvider('KETAMA_CRC');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_KETAMA_CRC_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_KETAMA, Hashring::HASH_CRC);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('KETAMA_CRC_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_KETAMA_oneAtaTime_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_KETAMA, Hashring::HASH_DEFAULT);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('KETAMA_oneAtaTime_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_COMPATIBLE()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_COMPATIBLE);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port']);
        }
        $data = $this->findServerDataProvider('COMPATIBLE');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testGetServer_COMPATIBLE_WEIGHT()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_COMPATIBLE);
        $servers = $this->serverDataProvider();
        foreach ($servers as $server) {
            $hashring->add($server['host'].':'.$server['port'], $server['weight']);
        }
        $data = $this->findServerDataProvider('COMPATIBLE_WEIGHT');
        foreach ($data as $key=>$value) {
            static::assertEquals($value, substr($hashring->get($key),-5));
        }
    }

    public function testFailOver()
    {
        $hashring = new Hashring(Hashring::DISTRIBUTION_COMPATIBLE);
        $servers = ['cache', 'cache1', 'cache2', 'cache3', 'cache4', 'cache5', 'cache6'];
        $hashring->add($servers);
        $server = $hashring->get('foo');
        $hashring->failOver($server);
        $server2 = $hashring->get('foo');
        static::assertTrue(in_array($server, $servers));
        static::assertTrue(in_array($server2, $servers));
        static::assertNotEquals($server, $server2);
    }
}
