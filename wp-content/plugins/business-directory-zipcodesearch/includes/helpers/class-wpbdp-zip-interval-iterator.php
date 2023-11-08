<?php

/**
 * Allows iteration over part of the items in a ZIPCodeDB.
 *
 * @since 3.3
 */
class ZIPCodeDB_IntervalItemIterator implements Iterator {

    private $zipdb;
    private $start;
    private $end;

    public function __construct( &$zipdb, $start, $end ) {
        $this->zipdb = $zipdb;
        $this->start = intval( $start );
        $this->end   = intval( $end );
        $this->rewind();
    }

    public function rewind() {
        $this->zipdb->seek( $this->start );
        // $this->zipdb->current();
    }

    public function key() {
        return $this->zipdb->key();
    }

    public function current() {
        return $this->zipdb->current();
    }

    public function next() {
        $this->zipdb->next();
    }

    public function valid() {
        return $this->zipdb->key() <= $this->end && $this->zipdb->valid();
    }

}
