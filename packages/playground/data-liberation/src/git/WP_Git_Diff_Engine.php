<?php

class WP_Git_Diff_Engine {

    public function diff($oldString, $newString) {
        $oldLines = explode("\n", $oldString);
        $newLines = explode("\n", $newString);

        $lcs = $this->calculateLCS($oldLines, $newLines);

        $oldIndex = 0;
        $newIndex = 0;
        $changes = [];

        foreach ($lcs as $match) {
            while ($oldIndex < $match['oldIndex'] || $newIndex < $match['newIndex']) {
                if ($oldIndex < $match['oldIndex']) {
                    $changes[] = ['type' => '-', 'line' => $oldLines[$oldIndex], 'oldIndex' => $oldIndex, 'newIndex' => null];
                    $oldIndex++;
                }
                if ($newIndex < $match['newIndex']) {
                    $changes[] = ['type' => '+', 'line' => $newLines[$newIndex], 'oldIndex' => null, 'newIndex' => $newIndex];
                    $newIndex++;
                }
            }

            // Add matching line as context
            if ($oldIndex < count($oldLines) && $newIndex < count($newLines)) {
                $changes[] = ['type' => ' ', 'line' => $oldLines[$oldIndex], 'oldIndex' => $oldIndex, 'newIndex' => $newIndex];
                $oldIndex++;
                $newIndex++;
            }
        }

        // Add remaining lines
        while ($oldIndex < count($oldLines)) {
            $changes[] = ['type' => '-', 'line' => $oldLines[$oldIndex], 'oldIndex' => $oldIndex, 'newIndex' => null];
            $oldIndex++;
        }
        while ($newIndex < count($newLines)) {
            $changes[] = ['type' => '+', 'line' => $newLines[$newIndex], 'oldIndex' => null, 'newIndex' => $newIndex];
            $newIndex++;
        }

        return $changes;
    }
    
    public function formatAsGit($changes, $options = []) {
        $options['contextLines'] ??= 3;
        $options['a_source'] ??= 'a/string';
        $options['b_source'] ??= 'b/string';

        // Format the diff to Git-style with context
        $formattedDiff = "diff --git " . $options['a_source'] . " " . $options['b_source'] . "\n";
        $formattedDiff .= "--- " . $options['a_source'] . "\n";
        $formattedDiff .= "+++ " . $options['b_source'] . "\n";

        $changeBlocks = [];
        $currentBlock = [];

        $last_changed_lineno = null;
        foreach ($changes as $lineno => $change) {
            if ($change['type'] === ' ') {
                if(empty($currentBlock)) {
                    continue;
                }
                if($lineno - $last_changed_lineno > $options['contextLines']) {
                    $changeBlocks[] = $currentBlock;
                    $currentBlock = [];
                    continue;
                }
            } else if(empty($currentBlock)) {
                $offset = max(0, $lineno - $options['contextLines'] - 1);
                $length = min($options['contextLines'], count($changes) - $offset) - 1;
                $currentBlock = array_slice($changes, $offset, $length);
            }

            $currentBlock[] = $change;

            if($change['type'] !== ' ') {
                $last_changed_lineno = $lineno;
            }
        }

        if(!empty($currentBlock)) {
            $changeBlocks[] = $currentBlock;
        }

        foreach ($changeBlocks as $changes) {
            $block = '';
            $oldStart = null;
            $newStart = null;
            $oldCount = 0;
            $newCount = 0;

            foreach ($changes as $change) {
                if ($change['type'] !== '+') {
                    if ($oldStart === null) $oldStart = $change['oldIndex'];
                    $oldCount++;
                }
                if ($change['type'] !== '-') {
                    if ($newStart === null) $newStart = $change['newIndex'];
                    $newCount++;
                }
            }

            $oldStart = $oldStart !== null ? $oldStart + 1 : 0;
            $newStart = $newStart !== null ? $newStart + 1 : 0;

            $block .= sprintf("@@ -%d,%d +%d,%d @@", $oldStart, $oldCount, $newStart, $newCount);

            foreach ($changes as $change) {
                $block .= $change['type'] . ' ' . $change['line'] . "\n";
            }

            $formattedDiff .= $block;
        }

        return $formattedDiff;
    }

    private function calculateLCS($oldLines, $newLines) {
        $oldLen = count($oldLines);
        $newLen = count($newLines);
        $lcsMatrix = array_fill(0, $oldLen + 1, array_fill(0, $newLen + 1, 0));

        // Build the LCS matrix
        for ($i = 1; $i <= $oldLen; $i++) {
            for ($j = 1; $j <= $newLen; $j++) {
                if ($oldLines[$i - 1] === $newLines[$j - 1]) {
                    $lcsMatrix[$i][$j] = $lcsMatrix[$i - 1][$j - 1] + 1;
                } else {
                    $lcsMatrix[$i][$j] = max($lcsMatrix[$i - 1][$j], $lcsMatrix[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find the LCS
        $lcs = [];
        $i = $oldLen;
        $j = $newLen;
        while ($i > 0 && $j > 0) {
            if ($oldLines[$i - 1] === $newLines[$j - 1]) {
                $lcs[] = ['oldIndex' => $i - 1, 'newIndex' => $j - 1];
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

