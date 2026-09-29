<?php

namespace Tests\Feature;

use App\Listeners\RetenirCourrielsDeDemonstration;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

/**
 * Les adresses inventees du jeu de demonstration ne recoivent rien : leurs
 * retours en erreur feraient suspendre le compte d'envoi.
 */
class CourrielsDemonstrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
    }

    /** @return array<int, SentMessage> */
    private function envoyes(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }

    private function envoyer(array $a, array $cc = []): void
    {
        Mail::raw('Test', function (Message $m) use ($a, $cc) {
            $m->to($a)->subject('Test');
            if ($cc !== []) {
                $m->cc($cc);
            }
        });
    }

    public function test_une_adresse_de_demonstration_ne_recoit_rien(): void
    {
        $this->envoyer(['julie.vanacker19@client19.be']);
        $this->envoyer(['tom.gerard13@contact.be']);
        $this->envoyer(['test@nblogitrack-test.eu']);

        $this->assertCount(0, $this->envoyes());
    }

    public function test_une_vraie_adresse_recoit_son_courriel(): void
    {
        $this->envoyer(['souf_1080@hotmail.com']);

        $this->assertCount(1, $this->envoyes());
    }

    public function test_seule_la_vraie_adresse_reste_dans_un_envoi_mixte(): void
    {
        $this->envoyer(['vrai@gmail.com', 'faux@contact.be'], ['copie@client7.be']);

        $envoyes = $this->envoyes();
        $this->assertCount(1, $envoyes);

        $destinataires = array_map(fn ($a) => $a->getAddress(), $envoyes[0]->getEnvelope()->getRecipients());
        $this->assertSame(['vrai@gmail.com'], $destinataires);
    }

    public function test_une_liste_vide_laisse_tout_partir(): void
    {
        config(['mail.domaines_bloques' => []]);

        $this->envoyer(['tom.gerard13@contact.be']);

        $this->assertCount(1, $this->envoyes());
    }

    public function test_les_motifs_couvrent_les_sous_domaines_et_les_variantes(): void
    {
        $motifs = ['client*.be', 'contact.be'];

        $this->assertTrue(RetenirCourrielsDeDemonstration::bloquee('a@client123.be', $motifs));
        $this->assertTrue(RetenirCourrielsDeDemonstration::bloquee('a@mail.contact.be', $motifs));
        $this->assertTrue(RetenirCourrielsDeDemonstration::bloquee('A@CONTACT.BE', $motifs));
        $this->assertFalse(RetenirCourrielsDeDemonstration::bloquee('a@moncontact.be', $motifs));
        $this->assertFalse(RetenirCourrielsDeDemonstration::bloquee('a@gmail.com', $motifs));
    }

    public function test_le_domaine_de_l_entreprise_recoit_ses_courriels(): void
    {
        $this->envoyer(['client@nblogitrack.be']);

        $this->assertCount(1, $this->envoyes());
    }
}
