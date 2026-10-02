<?php

return [
    'enabled' => env('INTERNAL_SMART_SEARCH_ENABLED', true),
    'engine' => 'internal-smart-search-v2.1',
    'max_search_results' => (int) env('INTERNAL_SEARCH_MAX_RESULTS', 20),
    'max_list_items_in_answer' => (int) env('INTERNAL_SEARCH_MAX_ANSWER_ITEMS', 8),
];
