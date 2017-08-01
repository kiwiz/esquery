<?php

class ResultTest extends PHPUnit_Framework_TestCase {
    public function testConstructQuery() {
        $q = [ESQuery\Token::C_SEARCH,
            [ESQuery\Token::F_OR, [
                [ESQuery\Token::Q_QUERYSTRING, "a",
                    [["b"], true]
                ],
                [ESQuery\Token::F_AND, [
                    [ESQuery\Token::Q_QUERYSTRING, "c",
                        [["d"], true]
                    ],
                    [ESQuery\Token::X_LIST, "e", [
                        [["f"],true],
                        [["g"],true]
                    ], true]
                ]]
            ]]
        ];

        $expected = [
            [
                "ignore_unavailable" => true,
                "body" => [
                    "size" => 100,
                    "query" => ["bool" => ["filter" =>
                        ["bool" => ["should" => [
                            ["query_string" => ["query" => "b", "default_operator" => "AND", "allow_leading_wildcard" => true, "default_field" => "a"]],
                            ["bool" => ["filter" => [
                                ["query_string" => ["query" => "d", "default_operator" => "AND", "allow_leading_wildcard" => true, "default_field" => "c"]],
                                ["query_string" => ["default_field" => "e", "default_operator" => "OR", "query" => "f g"]]
                            ]]]
                        ]]]
                    ]
                ]]
            ],
            ["aggs" => [], "flatten" => true, "map" => [], "scroll" => false, "post_query" => false, "count" => false]
        ];
        $this->assertEquals($expected, (new ESQuery\Result)->constructQuery(null, $q, [], null, []));
    }
}
