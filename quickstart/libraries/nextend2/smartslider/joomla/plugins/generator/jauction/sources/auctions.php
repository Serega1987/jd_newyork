<?php
N2Loader::import('libraries.slider.generator.abstract', 'smartslider');


class N2GeneratorJAuctionAuctions extends N2GeneratorAbstract {

    protected $layout = 'article';


    public function renderFields($form) {
        parent::renderFields($form);

        $filter = new N2Tab($form, 'filter', n2_('Filter'));

        $source = new N2ElementGroup($filter, 'source', n2_('Source'));
        new N2ElementJAuctionCategories($source, 'sourcecategories', n2_('Category'), 0, array(
            'isMultiple' => true
        ));

        $limit = new N2ElementGroup($filter, 'limit', n2_('Limit'), array(
            'rowClass' => 'n2-expert'
        ));
        new N2ElementFilter($limit, 'sourcefeatured', n2_('Featured'), 0);
        new N2ElementFilter($limit, 'sourceauto', n2_('Auto'), 0);
        new N2ElementFilter($limit, 'sourceprivate', n2_('Private'), 0);

        new N2ElementJAuctionLanguages($limit, 'sourcelanguage', n2_('Language'), 0);
        new N2ElementMenuItems($limit, 'itemid', n2_('Menu item (item ID)'), 0);


        $_order = new N2Tab($form, 'order', n2_('Order by'));
        $order  = new N2ElementMixed($_order, 'productsorder', n2_('Order'), 'pn.created|*|desc');
        new N2ElementList($order, 'productsorder-1', n2_('Field'), '', array(
            'options' => array(
                ''            => n2_('None'),
                'pt.name'     => n2_('Product name'),
                'pn.created'  => n2_('Creation time'),
                'pn.modified' => n2_('Modification time'),
                'rand()'      => n2_('Random'),
            )
        ));

        new N2ElementRadio($order, 'productsorder-2', n2_('order'), '', array(
            'options' => array(
                'asc'  => n2_('Ascending'),
                'desc' => n2_('Descending')
            )
        ));

    }

    protected function _getData($count, $startIndex) {
        $where = array(
            'pn.publish = 1',
            "pn.prodtypid = (SELECT prodtypid FROM #__product_type WHERE namekey = 'auction')"
        );

        $category = array_map('intval', explode('||', $this->data->get('sourcecategories', '')));
        if (!in_array(0, $category) && count($category) > 0) {
            $where[] = 'pn.pid IN (SELECT pid FROM #__productcat_product WHERE catid IN(' . implode(',', $category) . ')) ';
        }

        $language = $this->data->get('sourcelanguage', '0');
        if ($language != 0) {
            $where[] = 'pt.lgid = ' . $language;
        }

        switch ($this->data->get('sourcefeatured', '0')) {
            case 1:
                $where[] = 'pn.featured = 1';
                break;
            case -1:
                $where[] = 'pn.featured = 0';
                break;
        }

        switch ($this->data->get('sourceauto', '0')) {
            case 1:
                $where[] = 'pa.auto = 1';
                break;
            case -1:
                $where[] = 'pa.auto = 0';
                break;
        }

        switch ($this->data->get('sourceprivate', '0')) {
            case 1:
                $where[] = 'pa.private = 1';
                break;
            case -1:
                $where[] = 'pa.private = 0';
                break;
        }

        $o     = '';
        $order = N2Parse::parse($this->data->get('productsorder', 'pn.created|*|desc'));
        if ($order[0]) {
            $o = ' ORDER BY ' . $order[0] . ' ' . $order[1] . ' ';
        }

        $query = "SELECT pn.pid, pn.longitude, pn.latitude, pn.location, pn.namekey, pn.price,
                  pt.description, pt.name, pt.introduction, pt.seotitle, pt.seodescription, pt.seokeywords,
                  pa.startingbid, pa.reservebid, pa. biddingstart, pa. biddingend,
                  (SELECT name FROM #__file_node WHERE filid IN (SELECT filid FROM #__product_images WHERE pid = pn.pid AND ordering = 1) LIMIT 1) AS image,
                  (SELECT type FROM #__file_node WHERE filid IN (SELECT filid FROM #__product_images WHERE pid = pn.pid AND ordering = 1) LIMIT 1) AS type
                  FROM #__product_node AS pn LEFT JOIN #__product_trans AS pt ON pn.pid = pt.pid LEFT JOIN #__product_auction AS pa ON pn.pid = pa.pid WHERE " . implode(' AND ', $where) . $o . " LIMIT " . $startIndex . ", " . $count;

        $model  = new N2Model('jauction_products');
        $result = $model->db->queryAll($query);

        $itemID = $this->data->get('itemid', '0');
        if ($itemID == '0') {
            $itemID = '';
        } else {
            $itemID = '&Itemid=' . $itemID;
        }

        $data = array();
        $url  = JURI::root(false);
        foreach ($result AS $product) {
            $r      = array(
                'title'              => $product['name'],
                'description'        => $product['description'],
                'introduction'       => $product['introduction'],
                'image'              => N2JoomlaImageFallBack::fallback($url, array(
                    !empty($product['image']) ? 'joobi/user/media/images/products/' . $product['image'] . '.' . $product['type'] : '',
                ), array(
                    @$product['description']
                )),
                'thumbnail'          => !empty($product['image']) ? '$/joobi/user/media/images/products/thumbnails/' . $product['image'] . '.' . $product['type'] : '',
                'oversize_image'     => !empty($product['image']) ? '$/joobi/user/media/images/products/oversize/' . $product['image'] . '.' . $product['type'] : '',
                'url'                => JRoute::_('index.php?option=com_jauction&controller=catalog&task=show&eid=' . $product['pid'] . $itemID),
                'startingbid'        => $product['startingbid'],
                'roundedstartingbid' => round(floatval($product['startingbid']), 2),
                'reservebid'         => $product['reservebid'],
                'roundedreservebid'  => round(floatval($product['reservebid']), 2),
                'buynow'             => $product['price'],
                'roundedbuynow'      => round(floatval($product['price']), 2),
                'biddingstart'       => date('Y-m-d H:i', $product['biddingstart']),
                'biddingend'         => date('Y-m-d H:i', $product['biddingend']),
                'location'           => $product['location'],
                'longitude'          => $product['longitude'],
                'latitude'           => $product['latitude'],
                'sku'                => $product['namekey'],
                'seotitle'           => $product['seotitle'],
                'seodescription'     => $product['seodescription'],
                'seokeywords'        => $product['seokeywords'],
                'id'                 => $product['pid']
            );
            $data[] = $r;
        }

        return $data;
    }
}