<?php

namespace ESQuery;

class Parser extends InternalParser {
    public function generateUrl($query_str, $from='now-15m', $to='now', $host='localhost:5601', $index='[logstash-]YYYY.MM.DD]') {
        list($settings, $query_list) = $this->parse($query_str);

        // Currently, we only support normal queries or aggregations. No joins nor transactions!
        $is_agg = false;
        for($i = 1; $i < count($query_list); ++$i) {
            if($query_list[$i][0] != \ESQuery\Token::C_AGG) {
                throw new Exception(sprintf('Unsupported query clause', $query_list[$i][0]));
            }
            if($query_list[$i][1] != \ESQuery\Token::A_TERMS) {
                throw new Exception(sprintf('Unsupported agg type', $query_list[$i][1]));
            }
            $is_agg = true;
        }

        // Time range to return results from. Defaults to [now-15m, now].
        $from = Util::get($settings, 'from', $from);
        $to = Util::get($settings, 'to', $to);

        $time = $this->parseTimeClause($from, $to);

        // Fields to display in Kibana. Defaults to none.
        $fields = Util::get($settings, 'fields', []);

        // Field to sort by with the order. Unfortunately, K4 only allows sorting on one field. Defaults to @timestamp, desc.
        $sort = $this->parseSortClause(Util::get($settings, 'sort', []));

        $query = $this->parseQueryClause($query_list[0][1]);

        $_g = [
            'time' => $time
        ];
        $_a = [
            'query' => [
                'query_string' => [
                    'default_operator' => 'AND',
                    'allow_leading_wildcard' => true,
                    'analyze_wildcard' => true,
                    'query' => $query,
                ]
            ],
        ];

        if($is_agg) {
            $_a = array_merge($_a, [
                'vis' => $this->parseAggregationClause(array_slice($query_list, 1)),
            ]);

            return sprintf("%s/#/visualize/create?%s", $host, http_build_query([
                'type' => 'histogram',
                'indexPattern' => $index,
                '_g' => \Kunststube\Rison\rison_encode($_g),
                '_a' => \Kunststube\Rison\rison_encode($_a),
            ]));
        } else {
            $_a = array_merge($_a, [
                'index' => $index,
                'interval' => 'auto',
            ]);
            if(!is_null($sort)) {
                $_a['sort'] = $sort;
            }
            if(count($fields)) {
                $_a['columns'] = $fields;
            }

            return sprintf("%s/#/discover?%s", $host, http_build_query([
                '_g' => \Kunststube\Rison\rison_encode($_g),
                '_a' => \Kunststube\Rison\rison_encode($_a),
            ]));
        }
    }

    private function parseQueryClause($node) {
        if(!count($node)) {
            return [];
        }

        switch($node[0]) {
            case Token::F_AND:
                $list = [];
                foreach($node[1] as $c_node) {
                    $list[] = $this->parseQueryClause($c_node);
                }
                return implode(' AND ', $list);

            case Token::F_OR:
                $list = [];
                foreach($node[1] as $c_node) {
                    $list[] = $this->parseQueryClause($c_node);
                }
                return '(' . implode(' OR ', $list) . ')';

            case Token::F_NOT:
                return '-(' . $this->parseQueryClause($node[1]) . ')';

            case Token::F_EXISTS:
                return '_exists_:' . Util::escapeString($node[1]);

            case Token::F_MISSING:
                return '_missing_:' . Util::escapeString($node[1]);

            case Token::F_TERM:
                return $node[1] . ':"' . str_replace('"', '\\"', $node[2]) . '"';

            case Token::F_TERMS:
                return $node[1] . ':(' . implode(' ', array_map(function($x) { return '"' . str_replace('"', '\\"', $x) . '"'; }, $node[2])) . ')';

            case Token::X_LIST:
                if(!is_array($node[2])) {
                    throw new Exception('Unsupported list type');
                }

                $arr = array_map(function($x) { return Util::escapeGroup($x[0], $x[1]); }, $node[2]);
                return $node[1] . ':(' . implode(' OR ', $arr) . ')';

            case Token::F_REGEX:
                return $node[1] . ':/' . $node[2] . '/';

            case Token::Q_QUERYSTRING:
                return (is_null($node[1]) ? '':($node[1] . ':')) . Util::escapeGroup($node[2][0], $node[2][1]);

            default:
                throw new Exception('Unknown filter type');
        }
    }

    private function parseAggregationClause($query_list) {
        $aggs = [
            ['id' => 1, 'params' => [], 'schema' => 'metric', 'type' => 'count'],
        ];
        $i = 2;

        foreach($query_list as $query) {
            $aggs[] = ['id' => $i, 'params' => [
                'field' => $query[2],
                'order' => 'desc',
                'orderBy' => 1,
                'size' => Util::get($query[3], 'size', 10),
            ], 'schema' => 'segment', 'type' => 'terms'];
            ++$i;
        }

        return [
            'aggs' => $aggs,
            'params' => [
                'addLegend' => 1,
                'addTimeMarker' => false,
                'addTooltip' => 1,
                'defaultYExtents' => false,
                'mode' => 'stacked',
                'scale' => 'linear',
                'setYExtents' => false,
                'shareYAxis' => 1,
                'times' => [],
                'yAxis' => [],
            ],
            'type' => 'histogram',
        ];
    }

    private function parseTimeClause($from, $to) {
        $mode = 'quick';
        if(ctype_digit($from)) {
            $from = (new \DateTime("@$from"))->format(\DateTime::ATOM);
            $to = (new \DateTime("@$to"))->format(\DateTime::ATOM);
            $mode = 'absolute';
        }

        return [
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function parseSortClause($sort_raw) {
        if(count($sort_raw) == 0) {
            return null;
        }

        return [
            $sort_raw[0][0],
            $sort_raw[0][1] == 0 ? 'asc':'desc'
        ];
    }
}
