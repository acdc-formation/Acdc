<?php

namespace ACDC\Tests\Support;

use ACDC\Support\QuizScore;
use PHPUnit\Framework\TestCase;

/**
 * Tests de non-régression du scoring quiz live (bug H2 corrigé en 3.25.101 :
 * le temps de réponse doit être borné côté serveur pour empêcher la triche).
 */
final class QuizScoreTest extends TestCase {

	public function test_reponse_fausse_donne_zero() {
		$this->assertSame( 0, QuizScore::kahoot( false, 0, 30 ) );
	}

	public function test_reponse_instantanee_donne_le_max() {
		$this->assertSame( 1000, QuizScore::kahoot( true, 0, 30 ) );
	}

	public function test_reponse_a_la_limite_donne_500() {
		$this->assertSame( 500, QuizScore::kahoot( true, 30000, 30 ) );
		$this->assertSame( 500, QuizScore::kahoot( true, 45000, 30 ) );
	}

	public function test_decroissance_lineaire() {
		// Moitié du temps → 1000 - 500*0.5 = 750.
		$this->assertSame( 750, QuizScore::kahoot( true, 15000, 30 ) );
	}

	public function test_sans_limite_de_temps() {
		$this->assertSame( 1000, QuizScore::kahoot( true, 99999, 0 ) );
	}

	/** Anti-triche : le temps serveur est autoritatif dès qu'il est connu (le client n'est pas fiable). */
	public function test_clamp_utilise_le_temps_serveur_autoritatif() {
		$this->assertSame( 8000, QuizScore::clampResponseMs( 0, 8000 ), 'response_ms=0 falsifié → temps serveur.' );
		$this->assertSame( 8000, QuizScore::clampResponseMs( 5000, 8000 ), 'client non fiable → temps serveur autoritatif.' );
		$this->assertSame( 8000, QuizScore::clampResponseMs( 999999, 8000 ), 'client exagéré → temps serveur.' );
	}

	public function test_clamp_jamais_negatif() {
		$this->assertSame( 0, QuizScore::clampResponseMs( -100, 0 ) );
	}

	public function test_clamp_serveur_inconnu_conserve_client() {
		$this->assertSame( 4200, QuizScore::clampResponseMs( 4200, 0 ) );
	}

	/** Le tricheur ne gagne plus 1000 pts : après clamp, son score reflète le temps réel. */
	public function test_scenario_anti_triche_complet() {
		$client_ms = 0;            // tentative de triche.
		$server_ms = 12000;        // 12 s réellement écoulées.
		$borne = QuizScore::clampResponseMs( $client_ms, $server_ms );
		$score = QuizScore::kahoot( true, $borne, 30 );
		$this->assertLessThan( 1000, $score );
		$this->assertSame( 800, $score ); // 1000 - 500*(12/30) = 800.
	}
}
