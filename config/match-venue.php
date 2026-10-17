<?php
// Official venue shared by all match forms and public pages.
const MATCH_VENUE_NAME = 'Complexe sportif Marie-Victorin';
const MATCH_VENUE_STREET = '7000 Bd Maurice-Duplessis';
const MATCH_VENUE_CITY = 'Montréal, QC H1G 0A1';
const MATCH_VENUE_ADDRESS = MATCH_VENUE_STREET . ', ' . MATCH_VENUE_CITY;
define('MATCH_VENUE_MAP_URL', 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(MATCH_VENUE_NAME . ', ' . MATCH_VENUE_ADDRESS));
