<?php

namespace ACDC\Tests\Support;

use ACDC\Support\EmargeStatus;
use PHPUnit\Framework\TestCase;

final class EmargeStatusTest extends TestCase {

	public function test_pas_de_fiche() {
		$r = EmargeStatus::deriveLabels( false, 0, 0, 0 );
		$this->assertSame( 'Non générée', $r['signature'] );
		$this->assertSame( 'Non générée', $r['presence'] );
	}

	public function test_fiche_vide() {
		$r = EmargeStatus::deriveLabels( true, 0, 0, 0 );
		$this->assertSame( 'En attente', $r['signature'] );
		$this->assertSame( 'En attente', $r['presence'] );
	}

	public function test_tous_signes_complete() {
		$r = EmargeStatus::deriveLabels( true, 5, 5, 0 );
		$this->assertSame( 'Complète', $r['signature'] );
		$this->assertSame( 'Complète', $r['presence'] );
	}

	public function test_partiellement_signe() {
		$r = EmargeStatus::deriveLabels( true, 5, 2, 1 );
		$this->assertSame( 'Partielle', $r['signature'] );
		$this->assertSame( 'Partielle', $r['presence'] );
	}

	public function test_aucun_signe_avec_absences() {
		$r = EmargeStatus::deriveLabels( true, 5, 0, 3 );
		$this->assertSame( 'En attente', $r['signature'] );
		$this->assertSame( 'Absences', $r['presence'] );
	}

	public function test_aucun_signe_sans_absence() {
		$r = EmargeStatus::deriveLabels( true, 5, 0, 0 );
		$this->assertSame( 'En attente', $r['signature'] );
		$this->assertSame( 'En attente', $r['presence'] );
	}

	public function test_count_statuses_objets() {
		$learners = array(
			(object) array( 'status' => 'signe' ),
			(object) array( 'status' => 'signe' ),
			(object) array( 'status' => 'absent' ),
			(object) array( 'status' => 'pending' ),
		);
		$c = EmargeStatus::countStatuses( $learners );
		$this->assertSame( 4, $c['total'] );
		$this->assertSame( 2, $c['signed'] );
		$this->assertSame( 1, $c['absent'] );
	}

	public function test_count_statuses_vide() {
		$c = EmargeStatus::countStatuses( array() );
		$this->assertSame( array( 'total' => 0, 'signed' => 0, 'absent' => 0 ), $c );
	}

	/** Chaîne complète : compter puis dériver reproduit le comportement attendu. */
	public function test_chaine_complete() {
		$learners = array(
			(object) array( 'status' => 'signe' ),
			(object) array( 'status' => 'signe' ),
			(object) array( 'status' => 'signe' ),
		);
		$c = EmargeStatus::countStatuses( $learners );
		$r = EmargeStatus::deriveLabels( true, $c['total'], $c['signed'], $c['absent'] );
		$this->assertSame( 'Complète', $r['presence'] );
	}
}
