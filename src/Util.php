<?php

namespace ESQuery;

class Util {
    public static function get($arr, $key, $default=null) {
        return array_key_exists($key, $arr) ? $arr[$key]:$default;
    }

    public static function exists($arr, $key) {
        return array_key_exists($key, $arr);
    }

    // Escape special characters in a query.
    public static function escapeString($str, $allow_wildcards=false) {
        $needles = [
            '\\', '+', '-', '=', '&&', '||', '>', '<', '!', '(', ')',
            '{', '}', '[', ']', '^', '"', '~', ':', '/', ' '
        ];
        $replacements = [
            '\\\\', '\\+', '\\-', '\\=', '\\&&', '\\||', '\\>', '\\<', '\\!', '\\(', '\\)',
            '\\{', '\\}', '\\[', '\\]', '\\^', '\\"', '\\~', '\\:', '\\/', '\\ '
        ];
        if(!$allow_wildcards) {
            $needles = array_merge($needles, ['*', '?']);
            $replacements = array_merge($replacements, ['\\*', '\\?']);
        }
        return str_replace($needles, $replacements, $str);
    }

    // Escape special characters in an array of query chunks.
    public static function escapeGroup($arr, $wildcard=false) {
        return implode('', array_map(function($x) use ($wildcard) {
            if(is_string($x)) {
                if($wildcard) {
                    return Util::escapeString($x);
                } else {
                    return '"' . str_replace('"', '\\"', $x) . '"';
                }
            } else if($x == Token::W_STAR) {
                return '*';
            } else if ($x == Token::W_QMARK) {
                return '?';
            }
        }, $arr));
    }

    // Parser helper. Flatten results into an array.
    public static function combine($first, $rest, $idx) {
        $ret = [];
        $ret[] = $first;

        foreach($rest as $val) {
            $ret[] = $val[$idx];
        }
        return $ret;
    }

    // Parser helper. Turn results into an associative array.
    public static function assoc($first, $rest, $idx) {
        $ret = [];
        $ret[$first[0]] = $first[1];

        foreach($rest as $val) {
            $ret[$val[$idx][0]] = $val[$idx][1];
        }
        return $ret;
    }

    /**
     * Generate a list of date-based indices.
     * @param string $format The index format.
     * @param string $interval The interval size (h,d,w,m,y).
     * @param int $from_ts Start timestamp.
     * @param int $to_ts End timestamp.
     * @return string[] List of indices.
     */
    public static function generateDateIndices($format, $interval, $from_ts, $to_ts) {
        $fmt_arr = [];
        $escaped = false;

        foreach(str_split($format) as $chr) {
            switch($chr) {
            case '[':
                $escaped = true;
                break;
            case ']':
                $escaped = false;
                break;
            default:
                $fmt_arr[] = $escaped ? "\\$chr":$chr;
                break;
            }
        }
        $fmt_str = implode('', $fmt_arr);

        $ret = [];
        $current = new \DateTime("@$from_ts");
        $to = new \DateTime("@$to_ts");

        $interval_map = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
        ];
        $interval_str = Util::get($interval_map, $interval, 'day');

        // Zero out the time component.
        $current->setTime($interval == 'h' ? $current->format('H'):0, 0);

        while ($current <= $to) {
            $ret[] = $current->format($fmt_str);
            $current = $current->modify("+1$interval_str");
        }

        return $ret;
    }
}
