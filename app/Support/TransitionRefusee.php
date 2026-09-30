<?php

namespace App\Support;

use RuntimeException;

/**
 * L'ordre n'est plus dans l'etat attendu : un autre utilisateur l'a fait
 * avancer entre l'affichage et la confirmation, ou la transition n'existe
 * pas dans le cycle de vie.
 */
class TransitionRefusee extends RuntimeException {}
