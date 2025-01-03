<?php

class WP_Git_Merge_Engine {

    public function three_way_merge($diff1, $diff2) {
        $merged = [];
        $conflicts = [];

        $index1 = 0;
        $index2 = 0;

        while ($index1 < count($diff1) || $index2 < count($diff2)) {
            if ($index1 < count($diff1) && $index2 < count($diff2)) {
                $change1 = $diff1[$index1];
                $change2 = $diff2[$index2];

                if ($change1['type'] === $change2['type'] && $change1['line'] === $change2['line']) {
                    $merged[] = $change1;
                    $index1++;
                    $index2++;
                } elseif ($change1['type'] === '+' && $change2['type'] === '+') {
                    if ($change1['line'] !== $change2['line']) {
                        $conflicts[] = ['type' => '!', 'line1' => $change1['line'], 'line2' => $change2['line']];
                        $index1++;
                        $index2++;
                    } else {
                        $merged[] = $change1;
                        $index1++;
                        $index2++;
                    }
                } elseif ($change1['type'] === '-' && $change2['type'] === '-') {
                    $merged[] = $change1;
                    $index1++;
                    $index2++;
                } elseif ($change1['type'] === '-' || $change2['type'] === '-') {
                    // Line removed in one diff but not the other
                    if ($change1['type'] === '-') {
                        $merged[] = $change1;
                    }
                    if ($change2['type'] === '-') {
                        $merged[] = $change2;
                    }
                    $index1++;
                    $index2++;
                } elseif ($change1['type'] === '+') {
                    $merged[] = $change1;
                    $index1++;
                } elseif ($change2['type'] === '+') {
                    $merged[] = $change2;
                    $index2++;
                } else {
                    $conflicts[] = ['type' => '!', 'line1' => $change1['line'], 'line2' => $change2['line']];
                    $index1++;
                    $index2++;
                }
            } elseif ($index1 < count($diff1)) {
                $merged[] = $diff1[$index1];
                $index1++;
            } elseif ($index2 < count($diff2)) {
                $merged[] = $diff2[$index2];
                $index2++;
            }
        }

        if (!empty($conflicts)) {
            foreach ($conflicts as $conflict) {
                $merged[] = ['type' => '!', 'line' => "CONFLICT: " . $conflict['line1'] . " | " . $conflict['line2']];
            }
        }

        return $merged;
    }

    public function diff($old_string, $new_string) {
        $old_lines = explode("\n", $old_string);
        $new_lines = explode("\n", $new_string);

        $lcs = $this->calculate_longest_common_subsequence($old_lines, $new_lines);

        $old_index = 0;
        $new_index = 0;
        $changes = [];

        foreach ($lcs as $match) {
            while ($old_index < $match['old_index'] || $new_index < $match['new_index']) {
                if ($old_index < $match['old_index']) {
                    $changes[] = ['type' => '-', 'line' => $old_lines[$old_index], 'old_index' => $old_index, 'new_index' => null];
                    $old_index++;
                }
                if ($new_index < $match['new_index']) {
                    $changes[] = ['type' => '+', 'line' => $new_lines[$new_index], 'old_index' => null, 'new_index' => $new_index];
                    $new_index++;
                }
            }

            // Add matching line as context
            if ($old_index < count($old_lines) && $new_index < count($new_lines)) {
                $changes[] = ['type' => ' ', 'line' => $old_lines[$old_index], 'old_index' => $old_index, 'new_index' => $new_index];
                $old_index++;
                $new_index++;
            }
        }

        // Add remaining lines
        while ($old_index < count($old_lines)) {
            $changes[] = ['type' => '-', 'line' => $old_lines[$old_index], 'old_index' => $old_index, 'new_index' => null];
            $old_index++;
        }
        while ($new_index < count($new_lines)) {
            $changes[] = ['type' => '+', 'line' => $new_lines[$new_index], 'old_index' => null, 'new_index' => $new_index];
            $new_index++;
        }

        return $changes;
    }
    
    public function format_as_git($changes, $options = []) {
        $options['contextLines'] ??= 3;
        $options['a_source'] ??= 'a/string';
        $options['b_source'] ??= 'b/string';

        // Format the diff to Git-style with context
        $formatted_diff = "diff --git " . $options['a_source'] . " " . $options['b_source'] . "\n";
        $formatted_diff .= "--- " . $options['a_source'] . "\n";
        $formatted_diff .= "+++ " . $options['b_source'] . "\n";

        $changed_blocks = [];
        $current_block = [];

        $last_changed_lineno = null;
        foreach ($changes as $lineno => $change) {
            if ($change['type'] === ' ') {
                if(empty($current_block)) {
                    continue;
                }
                if($lineno - $last_changed_lineno > $options['contextLines']) {
                    $changed_blocks[] = $current_block;
                    $current_block = [];
                    continue;
                }
            } else if(empty($current_block)) {
                $offset = max(0, $lineno - $options['contextLines'] - 1);
                $length = min($options['contextLines'], count($changes) - $offset) - 1;
                $current_block = array_slice($changes, $offset, $length);
            }

            $current_block[] = $change;

            if($change['type'] !== ' ') {
                $last_changed_lineno = $lineno;
            }
        }

        if(!empty($current_block)) {
            $changed_blocks[] = $current_block;
        }

        foreach ($changed_blocks as $changes) {
            $block = '';
            $old_start = null;
            $new_start = null;
            $oldCount = 0;
            $newCount = 0;

            foreach ($changes as $change) {
                if ($change['type'] !== '+') {
                    if ($old_start === null) $old_start = $change['old_index'];
                    $oldCount++;
                }
                if ($change['type'] !== '-') {
                    if ($new_start === null) $new_start = $change['new_index'];
                    $newCount++;
                }
            }

            $old_start = $old_start !== null ? $old_start + 1 : 0;
            $new_start = $new_start !== null ? $new_start + 1 : 0;

            $block .= sprintf("@@ -%d,%d +%d,%d @@", $old_start, $oldCount, $new_start, $newCount);

            foreach ($changes as $change) {
                $block .= $change['type'] . ' ' . $change['line'] . "\n";
            }

            $formatted_diff .= $block;
        }

        return $formatted_diff;
    }

    private function calculate_longest_common_subsequence($old_lines, $new_lines) {
        $old_len = count($old_lines);
        $new_len = count($new_lines);
        $lcsMatrix = array_fill(0, $old_len + 1, array_fill(0, $new_len + 1, 0));

        // Build the LCS matrix
        for ($i = 1; $i <= $old_len; $i++) {
            for ($j = 1; $j <= $new_len; $j++) {
                if ($old_lines[$i - 1] === $new_lines[$j - 1]) {
                    $lcsMatrix[$i][$j] = $lcsMatrix[$i - 1][$j - 1] + 1;
                } else {
                    $lcsMatrix[$i][$j] = max($lcsMatrix[$i - 1][$j], $lcsMatrix[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find the LCS
        $lcs = [];
        $i = $old_len;
        $j = $new_len;
        while ($i > 0 && $j > 0) {
            if ($old_lines[$i - 1] === $new_lines[$j - 1]) {
                $lcs[] = ['old_index' => $i - 1, 'new_index' => $j - 1];
                $i--;
                $j--;
            } elseif ($lcsMatrix[$i - 1][$j] >= $lcsMatrix[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return array_reverse($lcs);
    }

}

