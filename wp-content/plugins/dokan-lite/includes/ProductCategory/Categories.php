<?php

namespace WeDevs\Dokan\ProductCategory;

use WeDevs\Dokan\Cache;

class Categories {

    /**
     * @var array
     *
     * @since 3.6.2
     */
    private $categories = [];

    /**
     * This method will return category data
     *
     * @sience 3.6.2
     *
     * @return array
     */
    public function get() {
        $transient_key = function_exists( 'wpml_get_current_language' ) ? 'multistep_categories_' . wpml_get_current_language() : 'multistep_categories';

        $this->categories = Cache::get_transient( $transient_key );

        if ( false === $this->categories ) {
            //calculate category data
            $this->get_categories();
            // set category data to cache
            Cache::set_transient( $transient_key, $this->categories, '', MONTH_IN_SECONDS );
        }

        return $this->categories;
    }

    /**
     * This method will prepare category data
     *
     * @since 3.6.2
     *
     * @return void
     */
    private function get_categories() {
        global $wpdb;

        // get all categories
        $categories = $wpdb->get_results(
            "SELECT terms.term_id, terms.name, tax.parent AS parent_id FROM `{$wpdb->prefix}terms` AS terms
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS tax
            ON terms.term_id = tax.term_id
            WHERE tax.taxonomy = 'product_cat'",
            OBJECT_K
        );

        if ( empty( $categories ) ) {
            $this->categories = [];
            return;
        }

        // convert category data to array
        $this->categories = json_decode( wp_json_encode( $categories ), true );
        // we don't need old categories variable
        unset( $categories );

        foreach ( $this->categories as $category_id => $category_data ) {
            // set immediate child to a category
            $parent_id = $this->categories[ $category_id ]['parent_id'];

            if ( ! isset( $this->categories[ $category_id ]['children'] ) ) {
                $this->categories[ $category_id ]['children'] = [];
            }

            if ( ! empty( $parent_id ) ) {
                $this->categories[ $parent_id ]['children'][] = $category_id;
            }

            $this->recursively_get_parent_categories( $category_id );
        }
    }

    /**
     * This method will recursively get parent id of a category
     *
     * @sience 3.6.2
     *
     * @param int $current_item
     *
     * @return void
     */
    private function recursively_get_parent_categories( $current_item ) {
        $parent_id = intval( $this->categories[ $current_item ]['parent_id'] );

        // setting base condition to exit recursion
        if ( 0 === $parent_id ) {
            $this->categories[ $current_item ]['parents'] = [];
            $this->categories[ $current_item ]['breadcumb'][] = $this->categories[ $current_item ]['name'];
            // if parent category parents value is empty, no more recursion is needed
        } elseif ( isset( $this->categories[ $parent_id ]['parents'] ) && empty( $this->categories[ $parent_id ]['parents'] ) ) {
            $this->categories[ $current_item ]['parents'][] = $parent_id;
            $this->categories[ $current_item ]['breadcumb'][] = $this->categories[ $parent_id ]['name'];
            // if parent category parents value is not empty, set that value as current category parents
        } elseif ( ! empty( $this->categories[ $parent_id ]['parents'] ) ) {
            $this->categories[ $current_item ]['parents'] = array_merge( $this->categories[ $parent_id ]['parents'], [ $parent_id ] );
            $this->categories[ $current_item ]['breadcumb'] = array_merge( $this->categories[ $parent_id ]['breadcumb'], [ $this->categories[ $parent_id ]['name'], $this->categories[ $current_item ]['name'] ] );
            // otherwise, get parent category parents, then set current category parents
        } else {
            $this->recursively_get_parent_categories( $parent_id );
            $this->recursively_get_parent_categories( $current_item );
        }
    }
}
