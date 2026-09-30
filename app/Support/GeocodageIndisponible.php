<?php

namespace App\Support;

use RuntimeException;

/**
 * Photon n'a pas repondu : la localite n'a pas pu etre verifiee, ce qui ne
 * veut pas dire que l'adresse est fausse.
 */
final class GeocodageIndisponible extends RuntimeException {}
