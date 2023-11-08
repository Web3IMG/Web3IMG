<?php

/* --------------------------------------------------------------------

  Chevereto
  https://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
            <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  https://chevereto.com/license

  --------------------------------------------------------------------- */

namespace CHV;

use BadMethodCallException;
use DateTime;
use G;
use Exception;
use LogicException;

class Listing
{
    private $isApproved = 1;
    protected static $valid_types = ['images', 'albums', 'users'];
    protected static $valid_sort_types = ['date_gmt', 'size', 'views', 'id', 'image_count', 'name', 'title', 'username'];

    public $output;

    public $binds = [];

    public $type;

    private $category;

    private $where = '';

    private $tools;

    public $reverse = false;

    public function debugQuery()
    {
        if (!isset($this->query)) {
            throw new BadMethodCallException;
        }
        $params = [];
        foreach ($this->binds as $bind) {
            $params[] = $bind['param'] . '=' . $bind['value'];
        }
        return '# Dumped listing query'
            . "\n" . $this->query
            . "\n\n# Dumped query params"
            . "\n" . implode("\n", $params);
    }

    // Sets the `image_is_approved` flag
    public function setApproved($bool)
    {
        $this->isApproved = $bool;
    }

    // Sets the type of resource being listed
    public function setType($type)
    {
        $this->type = $type;
    }

    // Sets the offset (sql> LIMIT offset,limit)
    public function setOffset($offset)
    {
        $this->offset = intval($offset);
    }

    // Sets ID to seek next-to
    public function setSeek($seek)
    {
        if (strpos($seek, '.') !== false) {
            $explode = explode('.', $seek);
            $copy = $explode;
            end($explode);
            $last = key($explode);
            unset($copy[$last]);
            $array = [
                0 => implode('.', $copy),
                1 => decodeID($explode[$last])
            ];
            $this->seek = $array;
            return;
        }
        $decodeID = decodeID($seek);
        if (ctype_digit($decodeID)) {
            $this->seek = $decodeID;
            return;
        }
        $this->seek = $seek;
    }

    public function setReverse($bool)
    {
        $this->reverse = $bool;
    }

    public function setParamsHidden($params)
    {
        $this->params_hidden = $params;
    }

    // Sets the limit (sql> LIMIT offset,limit)
    public function setLimit($limit)
    {
        $this->limit = intval($limit);
    }

    public function setItemsPerPage($count)
    {
        $this->items_per_page = intval($count);
    }

    // Sets the sort type (sql> SORT BY sort_type)
    public function setSortType($sort_type)
    {
        $this->sort_type = $sort_type == 'date' ? 'date_gmt' : $sort_type;
    }

    // Sets the sort order (sql> DESC | ASC)
    public function setSortOrder($sort_order)
    {
        $this->sort_order = $sort_order;
    }

    // Sets the WHERE clause
    public function setWhere($where)
    {
        $this->where = !empty($where) ? $where : null;
    }

    // Sets the owner id of the content, usefull to add privacy
    public function setOwner($user_id)
    {
        $this->owner = $user_id;
    }

    // Sets the user id of the request, usefull to add privacy
    public function setRequester($user)
    {
        $this->requester = $user;
    }

    // Sets the category
    public function setCategory($category)
    {
        $this->category = (int) $category;
    }

    // Sets the privacy layer of this listing
    public function setPrivacy($privacy)
    {
        $this->privacy = $privacy;
    }

    // Sets the tools available for this listing (only if applies)
    public function setTools($tools = [])
    {
        $this->tools = $tools;
    }

    public function bind($param, $value, $type = null)
    {
        $this->binds[] = array(
            'param' => $param,
            'value' => $value,
            'type'  => $type
        );
    }

    /**
     * Do the thing
     * @Exeption 4xx
     */
    public function exec()
    {
        $this->validateInput();
        $tables = DB::getTables();
        if (!isset($this->requester)) {
            $this->requester = Login::getUser();
        } elseif (!is_array($this->requester)) {
            $this->requester = User::getSingle($this->requester, 'id');
        }
        if ($this->type == 'images') {
            $this->where = (empty($this->where) ? 'WHERE ' : ($this->where . ' AND ')) . 'image_is_approved = ' . (int) $this->isApproved;
        }
        $joins = [
            'images' => [
                'storages'    => 'LEFT JOIN ' . $tables['storages'] . ' ON ' . $tables['images'] . '.image_storage_id = ' . $tables['storages'] . '.storage_id',
                'users'        => 'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['images'] . '.image_user_id = ' . $tables['users'] . '.user_id',
                'albums'    => 'LEFT JOIN ' . $tables['albums'] . ' ON ' . $tables['images'] . '.image_album_id = ' . $tables['albums'] . '.album_id',
                'categories' => 'LEFT JOIN ' . $tables['categories'] . ' ON ' . $tables['images'] . '.image_category_id = ' . $tables['categories'] . '.category_id',
            ],
            'users' => [],
            'albums' => [
                'users'        => 'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['albums'] . '.album_user_id = ' . $tables['users'] . '.user_id'
            ]
        ];

        if ($this->type == 'users' && $this->sort_type == 'views') {
            $this->sort_type = 'content_views';
        }

        if (isset($this->params_hidden)) {
            $emptyTypeClauses['users'][] = 'user_image_count > 0 OR user_avatar_filename IS NOT NULL OR user_background_filename IS NOT NULL';
            if ($this->sort_type == 'views') {
                $emptyTypeClauses['albums'][] = 'album_views > 0';
                $emptyTypeClauses['images'][] = 'image_views > 0';
                $emptyTypeClauses['users'][] = 'user_content_views > 0';
            }
            if ($this->sort_type == 'likes') {
                $emptyTypeClauses['albums'][] = 'album_likes > 0';
                $emptyTypeClauses['images'][] = 'image_likes > 0';
                $emptyTypeClauses['users'][] = 'user_likes > 0';
            }
            if ($this->type == 'albums') {
                if (isset($this->params_hidden['album_min_image_count']) && $this->params_hidden['album_min_image_count'] > 0) {
                    $whereClauses[] = sprintf('album_image_count >= %d', $this->params_hidden['album_min_image_count']);
                } else {
                    $emptyTypeClauses['albums'][] = 'album_image_count > 0';
                }
            }
            if (array_key_exists($this->type, $emptyTypeClauses) && isset($this->params_hidden['hide_empty']) && $this->params_hidden['hide_empty'] == 1) {
                $whereClauses[] = '(' . implode(') AND (', $emptyTypeClauses[$this->type]) . ')';
            }
            if (isset($this->params_hidden['hide_banned']) && $this->params_hidden['hide_banned'] == 1) {
                $whereClauses[] = '(' . $tables['users'] . '.user_status IS NULL OR ' . $tables['users'] . '.user_status <> "banned"' . ')';
            }
            if ($this->type == 'images' && isset($this->params_hidden['is_animated']) && $this->params_hidden['is_animated'] == 1) {
                $whereClauses[] = 'image_is_animated = 1';
            }
            if (!empty($whereClauses)) {
                $whereClauses = join(' AND ', $whereClauses);
                $this->where = (empty($this->where) ? 'WHERE ' : ($this->where . ' AND ')) . $whereClauses;
            }
        }

        $type_singular = DB::getFieldPrefix($this->type);

        // Attempt to add explicit clauses
        if (!empty($this->where)) {
            $where_clauses = explode(' ', str_ireplace('WHERE ', null, $this->where));
            $where_arr = [];
            foreach ($where_clauses as $clause) {
                if (!preg_match('/\./', $clause)) {
                    $field_prefix = explode('_', $clause, 2)[0]; // field prefix (singular)
                    $table = DB::getTableFromFieldPrefix($field_prefix); // image -> chv_images
                    $table_prefix = G\get_app_setting('db_table_prefix');
                    $table_key = !empty($table_prefix) ? G\str_replace_first($table_prefix, null, $table) : $table;
                    if (array_key_exists($table_key, $tables)) {
                        $where_arr[] = $table . '.' . $clause;
                    } else {
                        $where_arr[] = $clause;
                    }
                } else {
                    $where_arr[] = $clause; // Let it be
                }
            }
            $this->where = 'WHERE ' . implode(' ', $where_arr);
        }

        // Social stuff
        if (version_compare(Settings::get('chevereto_version_installed'), '3.7.0', '>=')) {

            // Dynamic since v3.9.0
            $likes_join = 'LEFT JOIN ' . $tables['likes'] . ' ON ' . $tables['likes'] . '.like_content_type = "' . $type_singular . '" AND ' . $tables['likes'] . '.like_content_id = ' . $tables[$this->type] . '.' . $type_singular . '_id';

            if (preg_match('/like_user_id/', $this->where)) {
                $joins[$this->type]['likes'] = $likes_join;
            } elseif ($this->requester && $this->type !== 'users') {
                $joins[$this->type]['likes'] = $likes_join . ' AND ' . $tables['likes'] . '.like_user_id = ' . $this->requester['id'];
            }

            $follow_tpl_join = 'LEFT JOIN ' . $tables['follows'] . ' ON ' . $tables['follows'] . '.%FIELD = ' . $tables[$this->type] . '.' . ($this->type == 'users' ? 'user' : DB::getFieldPrefix($this->type) . '_user') . '_id';
            if (preg_match('/follow_user_id/', $this->where)) {
                $joins[$this->type]['follows'] = strtr($follow_tpl_join, ['%FIELD' => 'follow_followed_user_id']);
            }
            if (preg_match('/follow_followed_user_id/', $this->where)) {
                $joins[$this->type]['follows'] = strtr($follow_tpl_join, ['%FIELD' => 'follow_user_id']);
            }
        }

        // Add ID reservation clause
        if ($this->type == 'images') {
            $res_id_where = 'image_size > 0';
            if (empty($this->where)) {
                $this->where = 'WHERE ' . $res_id_where;
            } else {
                $this->where .= ' AND ' . $res_id_where;
            }
        }

        // Add category clause
        if ($this->type == 'images' && isset($this->category)) {
            $category_qry = $tables['images'] . '.image_category_id = ' . $this->category;
            if (empty($this->where)) {
                $this->where = 'WHERE ' . $category_qry;
            } else {
                $this->where .= ' AND ' . $category_qry;
            }
        }

        // Privacy layer
        if (
            !($this->requester['is_admin'] ?? false)
            && in_array($this->type, ['images', 'albums', 'users'])
            && (
                (!isset($this->owner) || !isset($this->requester)) || $this->owner !== $this->requester['id']
            )
        ) {
            if (empty($this->where)) {
                $this->where = 'WHERE ';
            } else {
                $this->where .= ' AND ';
            }

            $nsfw_off = $this->requester ? !$this->requester['show_nsfw_listings'] : !getSetting('show_nsfw_in_listings');

            switch ($this->type) {
                case 'images':
                    if ($nsfw_off) {
                        $nsfw_off_clause = $tables['images'] . '.image_nsfw = 0';
                        if ($this->requester) {
                            $this->where .= '(' . $nsfw_off_clause .  ' OR (' .  $tables['images'] . '.image_nsfw = 1 AND ' .  $tables['images'] . '.image_user_id = ' . $this->requester['id'] . ')) AND ';
                        } else {
                            $this->where .= $nsfw_off_clause . ' AND ';
                        }
                    }
                    break;
                case 'users':
                    $this->where .= $tables['users'] . '.user_is_private = 0';
                    break;
            }

            if ($this->type !== 'users') {
                if (getSetting('website_privacy_mode') == 'public' || $this->privacy == 'private_but_link' || getSetting('website_content_privacy_mode') == 'default') {
                    $this->where .= '(' . $tables['albums'] . '.album_privacy NOT IN';
                    $privacy_modes = ['private', 'private_but_link', 'custom'];
                    if($this->type === 'images') {
                        $privacy_modes[] = 'password';
                    }
                    if (isset($this->privacy) && in_array($this->privacy, $privacy_modes)) {
                        unset($privacy_modes[array_search($this->privacy, $privacy_modes)]);
                    }
                    $this->where .= " (" . "'" . implode("','", $privacy_modes) . "'" . ") ";
                    $this->where .=  "OR " . $tables['albums'] . '.album_privacy IS NULL';
                    if ($this->requester) {
                        $this->where .= ' OR ' . $tables['albums'] . '.album_user_id =' . $this->requester['id'];
                    }
                    $this->where .= ')';
                } else {
                    $injected_requester = !$this->requester['id'] ? 0 : $this->requester['id'];
                    $this->where .= '(' . $tables['albums'] . '.album_user_id = ' . $injected_requester;
                    $this->where .= $this->type == 'albums' ? ')' : (' OR ' . $tables['images'] . '.image_user_id = ' . $injected_requester . ')');
                }
            }
        }

        $sort_field = $type_singular . '_' . $this->sort_type;
        $key_field = $type_singular . '_id';

        if (isset($this->seek)) {
            if(G\ends_with('date_gmt', $this->sort_type)) {
                $d = DateTime::createFromFormat('Y-m-d H:i:s', $this->seek[0]);
                if(!$d || $d->format('Y-m-d H:i:s') !== $this->seek[0]) {
                    $this->seek = ['0000-01-01 00:00:00', $this->seek[1]];
                    // throw new LogicException(sprintf('Invalid ' . $sort_field . ' seek sorting `%s`', G\safe_html($this->seek[0])));
                }
            }
            if (empty($this->where)) {
                $this->where = 'WHERE ';
            } else {
                $this->where .= ' AND ';
            }
            if ($this->reverse) {
                $this->sort_order = $this->sort_order == 'asc' ? 'desc' : 'asc';
            }
            $signo = $this->sort_order == 'desc' ? '<=' : '>=';
            if ($this->sort_type == 'id') {
                $this->where .= $sort_field . ' ' . $signo . ' :seek';
                $this->bind(':seek', $this->seek);
            } else {
                $signo = $this->sort_order == 'desc' ? '<' : '>';
                $this->where .= '((' . $sort_field . ' ' . $signo . ' :seekSort) OR (' . $sort_field . ' = :seekSort AND ' . $key_field . ' ' . $signo . '= :seekKey))';
                $this->bind(':seekSort', $this->seek[0]);
                $this->bind(':seekKey', $this->seek[1]);
            }
        }

        if (!empty($this->where)) {
            $this->where = "\n" . $this->where;
        }

        $sort_order = strtoupper($this->sort_order);
        $table_order = DB::getTableFromFieldPrefix($type_singular);
        $order_by = "\n" . 'ORDER BY ';

        if (in_array($this->sort_type, ['name', 'title', 'username'])) {
            $order_by .= 'CAST('.$table_order.'.'.$sort_field.' as CHAR) '.$sort_order.', ';
            $order_by .= 'LENGTH('.$table_order.'.'.$sort_field.') '.$sort_order.', ';
        }

        $order_by .= '' . $table_order . '.' . $sort_field . ' ' . $sort_order;

        if ($this->sort_type != 'id') {
            $order_by .= ', ' . $table_order . '.' . $key_field . ' ' . $sort_order;
        }
        $limit = '';
        if ($this->limit > 0) {
            $limit = "\n" . 'LIMIT ' . ($this->limit + 1); // +1 allows to fetch "one extra" to detect prev/next pages
        }

        $base_table = $tables[$this->type];

        // Normal query
        if (empty($joins[$this->type])) {
            $query = 'SELECT * FROM ' . $base_table;
            $query .= $this->where . $order_by . $limit;
        // Alternative query
        } else {
            if (!empty($this->where)) {
                preg_match_all('/' . G\get_app_setting('db_table_prefix') . '([\w_]+)\./', $this->where, $where_tables);
                $where_tables = array_values(array_diff(array_unique($where_tables[1]), [$this->type]));
            } else {
                $where_tables = false;
            }
            if ($where_tables) {
                $join_tables = $where_tables;
            } else {
                reset($joins);
                $join_tables = [key($joins)];
            }

            $join = null;
            foreach ($join_tables as $join_table) {
                if (!empty($joins[$this->type][$join_table])) {
                    $join .= "\n" . $joins[$this->type][$join_table];
                    unset($joins[$this->type][$join_table]);
                }
            }

            // Get rid of the original Exif data (for listings)
            $null_db = $this->type == 'images' ? ', NULL as image_original_exifdata ' : null;

            $query = 'SELECT * ' . $null_db . 'FROM (SELECT * FROM ' . $base_table . $join . $this->where . $order_by . $limit . ') ' . $base_table;
            if (!empty($joins[$this->type])) {
                $query .=  "\n" . implode("\n", $joins[$this->type]);
            }
            $query .= $order_by;
        }

        $db = DB::getInstance();
        $this->query = $query;
        $db->query($this->query);
        foreach ($this->binds as $bind) {
            $db->bind($bind['param'], $bind['value'], $bind['type'] ?? null);
        }
        $this->output = $db->fetchAll();
        $this->output_count = $db->rowCount();
        $this->has_page_next = $db->rowCount() > $this->limit;
        if ($this->reverse) {
            $this->output = array_reverse($this->output);
        }
        $start = current($this->output);
        $end = end($this->output);
        $seekEnd = $end[$sort_field] ?? null;
        $seekStart = $start[$sort_field] ?? null;
        if ($this->sort_type == 'id') {
            $seekEnd = encodeID($seekEnd);
            $seekStart = encodeID($seekStart);
        } else {
            if(is_array($end)) {
                $seekEnd .= '.' . encodeID($end[$key_field]);
            }
            if(is_array($start)) {
                $seekStart .= '.' . encodeID($start[$key_field]);
            }
        }
        if(isset($seekEnd)) {
            $this->seekEnd = $seekEnd;
        }
        if(isset($seekStart)) {
            $this->seekStart = $seekStart;
        }
        if ($db->rowCount() > $this->limit) {
            array_pop($this->output);
        }
        $this->output = G\safe_html($this->output);
        $this->count = count($this->output);
        $this->nsfw = false;
        $this->output_assoc = [];
        $formatfn = 'CHV\\' . ucfirst(substr($this->type, 0, -1));
        foreach ($this->output as $k => $v) {
            $val = $formatfn::formatArray($v);
            $this->output_assoc[] = $val;
            if (!$this->nsfw && isset($val['nsfw']) && $val['nsfw']) {
                $this->nsfw = true;
            }
        }
        $this->sfw = !$this->nsfw;
        $this->has_page_prev = $this->offset > 0;
        G\handler::setCond('show_viewer_zero', isset($_REQUEST['viewer']));

        if ($this->type == 'albums' and $this->output) {
            $coverTpl = '(SELECT *
            FROM %tImages%
            LEFT JOIN %tStorages% ON %tImages%.image_storage_id = %tStorages%.storage_id
            WHERE image_id = (SELECT album_cover_id FROM %tAlbums% WHERE album_id = %ALBUM_ID%)
            AND %tImages%.image_is_approved = 1
            LIMIT 1)';
            $album_cover_qry_tpl = strtr($coverTpl, [
                '%tImages%' => $tables['images'],
                '%tStorages%' => $tables['storages'],
                '%tAlbums%' => $tables['albums'],
            ]);
            $albums_cover_qry_arr = [];
            $albums_mapping = [];
            foreach ($this->output as $k => &$album) {
                if ($album['album_image_count'] < 0) {
                    $album['album_image_count'] = 0;
                }
                $album['album_image_count_label'] = _n('image', 'images', $album['album_image_count']);
                $albums_cover_qry_arr[] = str_replace('%ALBUM_ID%', $album['album_id'], $album_cover_qry_tpl);
                $albums_mapping[$album['album_id']] = $k;
            }
            $albums_slice_qry = implode("\n" . 'UNION ALL ' . "\n", $albums_cover_qry_arr);
            $db->query($albums_slice_qry);
            $albums_slice = $db->fetchAll();
            if (!empty($albums_slice)) {
                foreach ($albums_slice as $slice) {
                    $album_key = $albums_mapping[$slice['image_album_id']];
                    if(!isset($this->output[$album_key]['album_images_slice'])) {
                        $this->output[$album_key]['album_images_slice'] = [];
                    }
                    $this->output[$album_key]['album_images_slice'][] = $slice;
                }
            }
        }
    }

    public static function getTabs($args = [], $expanded = false)
    {
        $default = [
            'list'        => true,
            'REQUEST'    => $_REQUEST,
            'listing'    => 'explore',
            'basename'    => G\get_route_name(),
            'tools'        => true,
            'tools_available' => [],
        ];
        $args = array_merge($default, $args);
        // Fix lazy basenames
        if (strpos($args['basename'], G\get_base_url()) !== false) {
            $args['basename'] = G\get_base_url() == $args['basename'] ? null : G\str_replace_first(G\get_base_url() . '/', null, $args['basename']);
        }
        $semantics = [
            'recent'    => [
                'icon' => 'fas fa-history',
                'label'        => _s('Recent'),
                'content'     => 'all',
                'sort'        => 'date_desc',
            ],
            'trending'    => [
                'icon' => 'fas fa-poll',
                'label'        => _s('Trending'),
                'content'     => 'all',
                'sort'        => 'views_desc',
            ],
        ];
        // Criteria -> images | albums | users
        // Criteria -> [CONTENT TABS]
        $criterias = [
            'top-users'        => [
                'icon' => 'fas fa-crown',
                'label'        => _s('Top users'),
                'sort'        => 'image_count_desc',
                'content'    => 'users',
            ],
            'most-recent'    => [
                'icon' => $semantics['recent']['icon'],
                'label'        => _s('Most recent'),
                'sort'        => 'date_desc',
                'content'    => 'all',
            ],
            'most-oldest'    => [
                'icon' => 'fas fa-fast-backward',
                'label'     => _s('Oldest'),
                'sort'        => 'date_asc',
                'content'    => 'all',
            ],
            'most-viewed'    => [
                'icon' => $semantics['trending']['icon'],
                'label'        => _s('Most viewed'),
                'sort'        => 'views_desc',
                'content'    => 'all',
            ],
        ];
        if (Settings::get('enable_likes')) {
            $semantics['popular'] = [
                'icon' => 'fas fa-heart',
                'label'    => _s('Popular'),
                'content' => 'all',
                'sort'    => 'likes_desc',
            ];
            $criterias['most-liked'] = [
                'icon' => 'fas fa-heart',
                'label'    => _s('Most liked'),
                'sort'    => 'likes_desc',
                'content' => 'all',
            ];
        }
        $criterias['album-az-asc'] = [
            'icon' => 'fas fa-sort-alpha-down',
            'label'        => 'AZ',
            'sort'        => 'name_asc',
            'content'    => 'albums',
        ];
        $criterias['image-az-asc'] = [
            'icon' => 'fas fa-sort-alpha-down',
            'label'        => 'AZ',
            'sort'        => 'title_asc',
            'content'    => 'images',
        ];
        $criterias['user-az-asc'] = [
            'icon' => 'fas fa-sort-alpha-down',
            'label'        => 'AZ',
            'sort'        => 'username_asc',
            'content'    => 'users',
        ];
        if (isset($args['order'])) {
            $criterias = array_merge(array_flip($args['order']), $criterias);
        }
        $listings = [
            'explore'    => [
                'label'        => _s('Explore'),
                'content'    => 'images',
            ],
            'animated'    => [
                'label'        => _s('Animated'),
                'content'    => 'images',
                'where'        => 'image_is_animated = 1',
                'semantic'    => true,
            ],
            'search'    => [
                'label'        => _s('Search'),
                'content'    => 'all',
            ],
            'users'    => [
                'icon' => 'fas fa-users',
                'label'        => _s('People'),
                'content'    => 'users',
            ],
            'images'    => [
                'icon' => 'fas fa-image',
                'label'        => _s('Images'),
                'content'    => 'images',
            ],
            'albums'    => [
                'icon' => 'fas fa-images',
                'label'        => _s('Albums'),
                'content'    => 'albums',
            ],
        ];
        $listings = array_merge($listings, $semantics);
        $parameters = [];
        if(isset($args['listing'], $listings[$args['listing']])) {
            $parameters = $listings[$args['listing']];
        }

        if (isset($args['exclude_criterias']) && is_array($args['exclude_criterias'])) {
            foreach ($args['exclude_criterias'] as $exclude) {
                if (array_key_exists($exclude, $criterias)) {
                    unset($criterias[$exclude]);
                }
            }
        }

        // Content -> most recent | oldest | most viewed | most liked
        // Content -> [CRITERIA TABS]
        $contents = [
            'images' => [
                'icon' => $listings['images']['icon'],
                'label' => _s('Images'),
            ],
            'albums' => [
                'icon' => $listings['albums']['icon'],
                'label' => _s('Albums'),
            ],
            'users' => [
                'icon' => $listings['users']['icon'],
                'label' => _s('Users'),
            ]
        ];
        $i = 0;
        $currentKey = null;
        if(!isset($parameters['content'])) {
            $parameters['content'] = '';
        }
        $iterate = ($parameters['content'] == 'all' ? $contents : (isset($parameters['semantic']) ? $semantics : $criterias));

        foreach ($iterate as $k => $v) {
            if ($parameters['content'] == 'all') {
                $content = $k;
                $id = 'list-' . $args['listing'] . '-' . $content; // list-popular-images
                $sort = $parameters['sort'] ?? 'date_desc';
            } else {
                $content = $parameters['content'];
                if ($v['content'] !== 'all' && $v['content'] !== $content) {
                    continue;
                }
                $id = 'list-' . $k; // list-most-oldest
                $sort = $v['sort'];
            }
            if (!$content) {
                $content = 'images'; // explore
            }
            $basename = $args['basename'];
            $default_params = [
                'list' => $content,
                'sort' => $sort,
                'page' => '1',
            ];
            $params = $args['params'] ?? $default_params;
            if(isset($args['params_remove_keys'])) {
                foreach ((array) $args['params_remove_keys'] as $key) {
                    unset($params[$key]);
                }
            }
            if (isset($args['params']) && is_array($args['params']) && array_key_exists('q', $args['params']) && $args['listing'] == 'search') {
                $args['params_hidden']['list'] = $content;
                $basename .= '/' . $content;
            }
            if(isset($args['params_hidden'])) {
                foreach ((array) $args['params_hidden'] as $kk => $vv) {
                    if (array_key_exists($kk, $params)) {
                        unset($params[$kk]);
                    }
                }
            }
            $http_build_query = http_build_query($params);
            $url = (filter_var($basename, FILTER_VALIDATE_URL) ? rtrim($basename, '/') : G\get_base_url($basename)) . '/?' . $http_build_query;

            $current = isset($args['REQUEST'], $args['REQUEST']['sort']) ? $args['REQUEST']['sort'] == ($v['sort'] ?? false) : false;
            if ($i == 0 && !$current) {
                $current = !isset($args['REQUEST']['sort']);
            }
            if ($current && is_null($currentKey)) {
                $currentKey = $i;
            }

            $tab = [
                'icon' => $v['icon'] ?? null,
                'list'                => (bool) $args['list'],
                'tools'                => $content == 'users' ? false : (bool) $args['tools'],
                'tools_available'    => $args['tools_available'],
                'label'                => $v['label'],
                'id'                    => $id,
                'params'            => $http_build_query,
                'current'            => false,
                'type'                => $content,
                'url'                    => $url
            ];

            if ($args['tools_available'] && !G\Handler::getCond('allowed_to_delete_content') && array_key_exists('delete', $args['tools_available'])) {
                unset($args['tools_available']['delete']);
            }
            if ($args['tools_available'] == null) {
                unset($tab['tools_available']);
            }

            if (isset($args['params_hidden'])) {
                $tab['params_hidden'] = http_build_query($args['params_hidden']);
            }

            $tabs[] = $tab;
            unset($id, $params, $basename, $http_build_query, $content, $current);
            $i++;
        }

        if (is_null($currentKey)) {
            $currentKey = 0;
            if ($parameters['content'] == 'all') {
                foreach ($tabs as $k => &$v) {
                    if (isset($args['REQUEST']['list']) && $v['type'] == $args['REQUEST']['list']) {
                        $currentKey = $k;
                        break;
                    }
                }
            }
        }

        $tabs[$currentKey]['current'] = 1;

        if ($expanded) {
            return ['tabs' => $tabs, 'currentKey' => $currentKey];
        }

        return $tabs;
    }

    /**
     * validate_input aka "first stage validation"
     * This checks for valid input source data before exec
     * @Exception 1XX
     */
    protected function validateInput()
    {
        self::setValidSortTypes();

        if (empty($this->offset)) {
            $this->offset = 0;
        }

        // Missing values
        $check_missing = ['type', 'offset', 'limit', 'sort_type', 'sort_order'];
        missing_values_to_exception($this, 'CHV\ListingException', $check_missing, 100);

        // Validate type
        if (!in_array($this->type, self::$valid_types)) {
            throw new Exception('Invalid $type "' . $this->type . '"', 110);
        }

        if ($this->offset < 0 || $this->limit < 0) {
            throw new Exception('Limit integrity violation', 121);
        }

        // Validate sort type
        if (!in_array($this->sort_type, self::$valid_sort_types)) {
            throw new Exception('Invalid $sort_type "' . $this->sort_type . '"', 130);
        }

        // Validate sort order
        if (!preg_match('/^(asc|desc)$/', $this->sort_order)) {
            throw new Exception('Invalid $sort_order "' . $this->sort_order . '"', 140);
        }
    }

    // Handler for all those switcheable sort options (based on on/off settings)
    protected static function setValidSortTypes()
    {
        if (getSetting('enable_likes') and !in_array('likes', self::$valid_sort_types)) {
            array_push(self::$valid_sort_types, 'likes');
        }
    }

    public function htmlOutput($tpl_list = null)
    {

        if (!is_array($this->output)) {
            return;
        }

        if (is_null($tpl_list)) {
            $tpl_list = $this->type ?: 'images';
        }

        $directory = new \RecursiveDirectoryIterator(G_APP_PATH_THEME . 'tpl_list_item/');
        $iterator = new \RecursiveIteratorIterator($directory);
        $regex  = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        $list_item_template = [];

        foreach ($regex as $file) {
            $file = G\forward_slash($file[0]);
            $key = preg_replace('/\\.[^.\\s]{3,4}$/', '', str_replace(G_APP_PATH_THEME, null, $file));
            $override_file = G\str_replace_first(G_APP_PATH_THEME, G_APP_PATH_THEME . 'overrides/', $file);
            if (is_readable($override_file)) {
                $file = $override_file;
            }
            ob_start();
            require($file);
            $file_get_contents = ob_get_contents();
            ob_end_clean();
            $list_item_template[$key] = $file_get_contents;
        }

        $html_output = '';
        $tpl_list = preg_replace('/s$/', '', $tpl_list);
        if (function_exists('get_peafowl_item_list')) {
            $render = 'get_peafowl_item_list';
        } else {
            $render = 'CHV\Render\get_peafowl_item_list';
        }
        $tools = $this->tools ?: [];
        $requester = Login::getUser();

        foreach ($this->output as $row) {
            switch ($tpl_list) {
                case 'image':
                case 'user/image':
                case 'album/image':
                default: // key thing here...
                    $Class = 'CHV\Image';
                    break;
                case 'album':
                case 'user/album':
                    $Class = 'CHV\Album';
                    break;
                case 'user':
                case 'user/user':
                    $Class = 'CHV\User';
                    break;
            }
            $item = $Class::formatArray($row);
            $html_output .= $render($item, $list_item_template, $tools, $tpl_list, $requester);
        }

        return $html_output;
    }

    public static function getAlbumHtml($album_id, $template = 'user/albums')
    {
        $album = new Listing;
        $album->setType('albums');
        $album->setOffset(0);
        $album->setLimit(1);
        $album->setSortType('date');
        $album->setSortOrder('desc');
        $album->setWhere('WHERE album_id=:album_id');
        $album->bind(':album_id', $album_id);
        $album->exec();
        return $album->htmlOutput($template);
    }

    public static function getParams($json_call = false)
    {
        self::setValidSortTypes();

        $items_per_page = getSetting('listing_items_per_page');
        $listing_pagination_mode = getSetting('listing_pagination_mode');

        $params = [];
        $params['offset'] = 0;
        $params['items_per_page'] = $items_per_page;

        if (!$json_call and $listing_pagination_mode == 'endless') {
            $params['page'] = max(intval($_REQUEST['page'] ?? 0), 1);
            $params['limit'] = $params['items_per_page'] * $params['page'];
            // $params['offset'] = 0;

            // Switch endless to classic if we are dealing with large listings (from GET)
            if ($params['limit'] > getSetting('listing_safe_count')) {
                $listing_pagination_mode = 'classic';
                Settings::setValue('listing_pagination_mode', $listing_pagination_mode);
            }
        }

        if (isset($_REQUEST['pagination']) or $listing_pagination_mode == 'classic') { // Static single page display
            $params['page'] = !empty($_REQUEST['page']) ? intval($_REQUEST['page'] ?? 0) - 1 : 0;
            $params['limit'] = $params['items_per_page'];
            $params['offset'] = $params['page'] * $params['limit']; // TODO: Get rid
        }

        if ($json_call) {
            $params = array_merge($params, [
                'page'    => !empty($_REQUEST['page']) ? $_REQUEST['page'] - 1 : 0,
                'limit'    => $items_per_page
            ]);
            $params['offset'] = $params['page'] * $params['limit'] + ($_REQUEST['offset'] ?? 0);
        }

        $default_sort = [
            0 => 'date',
            1 => 'desc'
        ];

        preg_match('/(.*)_(asc|desc)/', $_REQUEST['sort'] ?? '', $sort_matches);
        $params['sort'] = array_slice($sort_matches, 1);


        // Empty sort
        if (count($params['sort']) !== 2) {
            $params['sort'] = $default_sort;
        }

        // Check sort type
        if (!in_array($params['sort'][0], self::$valid_sort_types)) {
            $params['sort'][0] = $default_sort[0];
        }
        // Check sort order
        if (!in_array($params['sort'][1], ['asc', 'desc'])) {
            $params['sort'][1] = $default_sort[1];
        }

        if (!empty($_REQUEST['seek'])) {
            $params['seek'] = $_REQUEST['seek'];



        } elseif (!empty($_REQUEST['peek'])) {
            $params['seek'] = $_REQUEST['peek'];
            $params['reverse'] = true;
        }

        $params['page_show'] = !empty($_REQUEST['page']) ? intval($_REQUEST['page']) : null;

        return $params;
    }
}
