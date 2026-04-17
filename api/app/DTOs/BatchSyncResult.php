<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * 批量同步结果 DTO
 *
 * 聚合多个 SyncResult，提供统计摘要（成功/失败/跳过计数）和耗时信息。
 */
class BatchSyncResult
{
    public function __construct(
        /** 总处理商品数 */
        public readonly int $total,
        /** 成功同步数 */
        public readonly int $succeeded,
        /** 同步失败数 */
        public readonly int $failed,
        /** 跳过数（如商品未激活等） */
        public readonly int $skipped,
        /** 各商品的 SyncResult 详情 @var SyncResult[] */
        public readonly array $results,
        /** 批量同步耗时（秒） */
        public readonly float $duration,
    ) {}

    /**
     * 从 SyncResult 数组构建 BatchSyncResult
     *
     * @param  SyncResult[] $results
     * @param  int          $skipped
     * @param  float        $duration  耗时（秒）
     */
    public static function fromResults(array $results, int $skipped = 0, float $duration = 0.0): self
    {
        $succeeded = 0;
        $failed    = 0;

        foreach ($results as $result) {
            $result->success ? $succeeded++ : $failed++;
        }

        return new self(
            total: count($results) + $skipped,
            succeeded: $succeeded,
            failed: $failed,
            skipped: $skipped,
            results: $results,
            duration: round($duration, 2),
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'total'     => $this->total,
            'succeeded' => $this->succeeded,
            'failed'    => $this->failed,
            'skipped'   => $this->skipped,
            'duration'  => $this->duration,
            'results'   => array_map(fn (SyncResult $r) => $r->toArray(), $this->results),
        ];
    }
}
