<?php

namespace NextDeveloper\Marketplace\Helpers;

class XmlMappingHelper
{
    public static function getRackdepoMapping(): array
    {
        return [
            'root_node' => 'root',
            'item_node' => 'item',
            'fields' => [
                'basic_info' => [
                    'id' => ['path' => 'id', 'type' => 'int'],
                    'stock_code' => ['path' => 'stockCode', 'type' => 'string'],
                    'label' => ['path' => 'label', 'type' => 'string'],
                    'status' => ['path' => 'status', 'type' => 'int'],
                    'brand' => ['path' => 'brand', 'type' => 'string'],
                    'search_keywords' => ['path' => 'searchKeywords', 'type' => 'string'],
                ],
                'category' => [
                    'main_category' => [
                        'name' => ['path' => 'mainCategory', 'type' => 'string'],
                        'id' => ['path' => 'mainCategoryId', 'type' => 'int'],
                    ],
                    'sub_category' => [
                        'name' => ['path' => 'category', 'type' => 'string'],
                        'id' => ['path' => 'categoryId', 'type' => 'int'],
                    ],
                    'product_category_label' => ['path' => 'productCategoryLabel', 'type' => 'string'],
                ],
                'pricing' => [
                    'market_price' => ['path' => 'marketPrice', 'type' => 'string'],
                    'price1' => ['path' => 'price1', 'type' => 'float'],
                    'price2' => ['path' => 'price2', 'type' => 'float'],
                    'tax' => ['path' => 'tax', 'type' => 'int'],
                    'currency' => ['path' => 'currencyAbbr', 'type' => 'string'],
                    'system_currency' => ['path' => 'systemCurrency', 'type' => 'string'],
                    'price_with_tax' => ['path' => 'priceWithTax', 'type' => 'float'],
                    'price_tax_with_cur' => ['path' => 'priceTaxWithCur', 'type' => 'float'],
                ],
                'stock' => [
                    'amount' => ['path' => 'stockAmount', 'type' => 'int'],
                    'status' => ['path' => 'stock', 'type' => 'string'],
                    'type' => ['path' => 'stockType', 'type' => 'string'],
                ],
                'warranty' => ['path' => 'warranty', 'type' => 'int'],
                'images' => [
                    'picture1' => ['path' => 'picture1Path', 'type' => 'string'],
                    'picture2' => ['path' => 'picture2Path', 'type' => 'string'],
                    'picture3' => ['path' => 'picture3Path', 'type' => 'string'],
                    'picture4' => ['path' => 'picture4Path', 'type' => 'string'],
                    'picture5' => ['path' => 'picture5Path', 'type' => 'string'],
                ],
            ]
        ];
    }
}
