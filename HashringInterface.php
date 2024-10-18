<?php
namespace Tanbolt\Hashring;

/**
 * Interface HashRingInterface
 * @package Migorn\HashRing
 */
interface HashringInterface
{
    /**
     * 添加 node 节点
     * @param array|string $nodes
     * @return $this
     */
    public function add($nodes);

    /**
     * 删除指定 node 节点
     * @param string $node
     * @return $this
     */
    public function remove(string $node);

    /**
     * 将指定 node 节点设置为故障，与 remove 不同，故障节点不会被删除，仍参与分配运算，但当分配到故障节点会自动跳到下一个
     * @param string $node
     * @return $this
     */
    public function failOver(string $node);

    /**
     * 获取所有已添加 node 节点列表
     * @return array { key => [server=>string, weight=>int, fail=>bool] ... }
     */
    public function all();

    /**
     * 获取指定 $key 分配到的 node 节点
     * @param string $key
     * @return ?string
     */
    public function get(string $key);
}
