<?php

if (!function_exists('retryCall')) {
    /**
     * Retries the callback till it's positive or exceeded the retry count.
     *
     * @param int $retry Number of retries.
     * @param callable $callback The function to be called.
     * @return bool The result.
     */
    function retryCall(int $retry = 0, callable $callback): bool {
        do {
            $result = $callback($retry - 1);
        } while ($retry >= 1 && $result == false);

        return $result;
    }
}