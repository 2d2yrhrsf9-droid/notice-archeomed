<?php
/**
 * Page de réglages du Formulaire des notices d’archéologie médiévale.
 *
 * Permet de saisir depuis l'administration les valeurs qui figuraient
 * auparavant en dur dans le code : clés Turnstile et adresse de la rédaction.
 *
 * Ordre de priorité pour chaque valeur :
 *   1. la constante définie dans wp-config.php, si elle existe ;
 *   2. la valeur enregistrée dans cette page de réglages ;
 *   3. la valeur par défaut inscrite dans le code.
 *
 * Ainsi, un site qui déclare NA_TURNSTILE_SECRET dans wp-config.php garde ce
 * fonctionnement, et les autres passent par l'interface sans toucher un fichier.
 *
 * @package Notice_Archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Settings {

	const OPTION_GROUP = 'notice_archeomed_settings';
	const OPTION_NAME  = 'notice_archeomed_options';
	const PAGE_SLUG    = 'notice-archeomed';

	/**
	 * Valeurs par défaut, reprises de la version précédente du plugin.
	 */
	private static $defaults = array(
		'dest_email'         => '',
		'turnstile_site'     => '0x4AAAAAADnqPBVc6ZpaeiEi',
		'turnstile_secret'   => '',
		// « differe » ou « immediat ». Le différé rend la main à l'auteur en
		// une fraction de seconde et laisse les courriels partir après ; c'est
		// ce qui permet à plusieurs personnes de déposer en même temps.
		'mode_envoi'         => 'differe',
		// « locale », « turnstile » ou « les_deux ». Locale par défaut : elle
		// ne dépend d'aucun service tiers, et un serveur qui ne peut pas
		// joindre Cloudflare — hébergement institutionnel derrière un proxy
		// filtrant — n'a alors rien à régler pour que le formulaire protège.
		'protection'         => 'locale',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Renvoie une option, en donnant la priorité à la constante wp-config.php.
	 */
	public static function get( $key ) {
		$constants = array(
			'turnstile_secret' => 'NA_TURNSTILE_SECRET',
			'turnstile_site'   => 'NA_TURNSTILE_SITE',
			'dest_email'       => 'NA_DEST_EMAIL',
			'mode_envoi'       => 'NA_MODE_ENVOI',
			'protection'       => 'NA_PROTECTION',
		);
		if ( isset( $constants[ $key ] ) && defined( $constants[ $key ] ) ) {
			$value = constant( $constants[ $key ] );
			if ( '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		$options = get_option( self::OPTION_NAME, array() );
		if ( isset( $options[ $key ] ) && '' !== trim( (string) $options[ $key ] ) ) {
			return trim( (string) $options[ $key ] );
		}
		return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : '';
	}

	/**
	 * Indique si la valeur provient d'une constante, auquel cas le champ du
	 * formulaire est affiché en lecture seule pour éviter toute confusion.
	 */
	public static function is_locked( $key ) {
		$constants = array(
			'turnstile_secret' => 'NA_TURNSTILE_SECRET',
			'turnstile_site'   => 'NA_TURNSTILE_SITE',
			'dest_email'       => 'NA_DEST_EMAIL',
			'mode_envoi'       => 'NA_MODE_ENVOI',
			'protection'       => 'NA_PROTECTION',
		);
		return isset( $constants[ $key ] )
			&& defined( $constants[ $key ] )
			&& '' !== trim( (string) constant( $constants[ $key ] ) );
	}

	public function add_menu() {
		add_options_page(
			'Formulaire des notices d’archéologie médiévale',
			// Le libellé du menu tient dans une colonne étroite : le nom
			// complet s'y replierait sur trois lignes.
			'Notices d\'archéologie médiévale',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Nettoyage des valeurs soumises. Un champ « clé secrète » laissé vide ne
	 * doit pas effacer la clé déjà enregistrée : c'est le comportement attendu
	 * quand l'utilisateur enregistre la page sans retoucher ce champ.
	 */
	public function sanitize( $input ) {
		$current = get_option( self::OPTION_NAME, array() );
		$out     = is_array( $current ) ? $current : array();

		if ( isset( $input['dest_email'] ) ) {
			$email = sanitize_email( $input['dest_email'] );
			if ( '' === trim( $input['dest_email'] ) ) {
				$out['dest_email'] = '';
			} elseif ( is_email( $email ) ) {
				$out['dest_email'] = $email;
			} else {
				add_settings_error(
					self::OPTION_NAME,
					'dest_email',
					'L\'adresse de la rédaction n\'est pas une adresse électronique valide. L\'ancienne valeur a été conservée.',
					'error'
				);
			}
		}

		if ( isset( $input['turnstile_site'] ) ) {
			$out['turnstile_site'] = sanitize_text_field( $input['turnstile_site'] );
		}

		// La clé secrète n'est remplacée que si un nouveau contenu est fourni.
		if ( isset( $input['turnstile_secret'] ) && '' !== trim( $input['turnstile_secret'] ) ) {
			$out['turnstile_secret'] = sanitize_text_field( $input['turnstile_secret'] );
		}

		// Case à cocher explicite pour effacer la clé enregistrée.
		if ( ! empty( $input['clear_secret'] ) ) {
			$out['turnstile_secret'] = '';
		}

		if ( isset( $input['mode_envoi'] ) ) {
			$out['mode_envoi'] = 'immediat' === $input['mode_envoi'] ? 'immediat' : 'differe';
		}

		if ( isset( $input['protection'] ) ) {
			$out['protection'] = in_array(
				$input['protection'], array( 'locale', 'turnstile', 'les_deux' ), true )
				? $input['protection'] : 'locale';
		}

		return $out;
	}

	/**
	 * Teste la clé secrète auprès de Cloudflare. On envoie volontairement un
	 * jeton invalide : si la clé est bonne, l'API répond « invalid-input-response » ;
	 * si la clé est mauvaise, elle répond « invalid-input-secret ». C'est donc
	 * un moyen de valider la clé sans avoir à résoudre un défi.
	 */
	private function test_secret( $secret ) {
		if ( '' === trim( $secret ) ) {
			return array( 'ok' => false, 'message' => 'Aucune clé secrète enregistrée.' );
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => 'test-invalide-verification-cle',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => 'Impossible de joindre Cloudflare : ' . $response->get_error_message(),
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array( 'ok' => false, 'message' => 'Réponse inattendue de Cloudflare.' );
		}
		$codes = isset( $body['error-codes'] ) ? (array) $body['error-codes'] : array();
		if ( in_array( 'invalid-input-secret', $codes, true ) || in_array( 'missing-input-secret', $codes, true ) ) {
			return array( 'ok' => false, 'message' => 'Cloudflare refuse cette clé secrète. Vérifiez qu\'elle correspond bien au site déclaré.' );
		}
		// Tout autre code signifie que la clé a été acceptée et que seul le
		// jeton de test a été rejeté, ce qui est le résultat attendu.
		return array( 'ok' => true, 'message' => 'La clé secrète est reconnue par Cloudflare.' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$test_result = null;
		if ( isset( $_POST['na_test_turnstile'] ) ) {
			check_admin_referer( 'na_test_turnstile' );
			$test_result = $this->test_secret( self::get( 'turnstile_secret' ) );
		}

		$secret_locked = self::is_locked( 'turnstile_secret' );
		$site_locked   = self::is_locked( 'turnstile_site' );
		$email_locked  = self::is_locked( 'dest_email' );
		$options       = get_option( self::OPTION_NAME, array() );
		$has_secret    = '' !== trim( self::get( 'turnstile_secret' ) );
		// Le formulaire n'est hors service que si Turnstile est bien ce sur
		// quoi il s'appuie. Avec la protection locale, il fonctionne sans clé.
		$turnstile_sert = in_array(
			self::get( 'protection' ), array( 'turnstile', 'les_deux' ), true );
		?>
		<div class="wrap">
			<h1>Formulaire des notices d’archéologie médiévale</h1>

			<?php if ( $turnstile_sert && ! $has_secret ) : ?>
				<div class="notice notice-error">
					<p><strong>Le formulaire est actuellement hors service.</strong> Sans clé secrète Turnstile, toutes les soumissions sont refusées. Renseignez la clé ci-dessous.</p>
				</div>
			<?php endif; ?>

			<?php if ( null !== $test_result ) : ?>
				<div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( $test_result['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php settings_errors( self::OPTION_NAME ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2>Protection anti-robot</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Comment le formulaire se protège</th>
						<td>
							<?php $prot = self::get( 'protection' ); $prot_locked = self::is_locked( 'protection' ); ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="locale" <?php checked( 'locale' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Curseur à glisser dans le formulaire <strong>(ne dépend de rien)</strong>
							</label>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="turnstile" <?php checked( 'turnstile' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Cloudflare Turnstile seul
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protection]"
									value="les_deux" <?php checked( 'les_deux' === $prot ); ?>
									<?php disabled( $prot_locked ); ?>>
								Les deux
							</label>
							<p class="description">
								<?php if ( $prot_locked ) : ?>
									Valeur imposée par la constante <code>NA_PROTECTION</code>.
								<?php else : ?>
									Turnstile suppose que <strong>le serveur</strong> puisse joindre
									Cloudflare. Derrière un proxy filtrant — hébergement institutionnel —
									il ne le peut pas&nbsp;: la vérification échoue, le plugin laisse
									passer pour ne pas perdre de notice, et la protection n'en est plus
									une. Le curseur posé dans le formulaire se vérifie sur place et
									fonctionne partout. Le test ci-dessous dit si Cloudflare est
									joignable depuis ce serveur.
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<h2>Cloudflare Turnstile</h2>
				<p>Ces clés se créent gratuitement sur <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">le tableau de bord Cloudflare</a>, rubrique Turnstile. La clé de site est publique ; la clé secrète ne doit jamais être diffusée.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="na_site">Clé de site</label></th>
						<td>
							<input type="text" id="na_site" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[turnstile_site]"
								value="<?php echo esc_attr( $site_locked ? self::get( 'turnstile_site' ) : ( isset( $options['turnstile_site'] ) ? $options['turnstile_site'] : self::get( 'turnstile_site' ) ) ); ?>"
								<?php disabled( $site_locked ); ?>>
							<?php if ( $site_locked ) : ?>
								<p class="description">Valeur imposée par la constante <code>NA_TURNSTILE_SITE</code> définie dans <code>wp-config.php</code>.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="na_secret">Clé secrète</label></th>
						<td>
							<?php if ( $secret_locked ) : ?>
								<input type="text" class="regular-text" value="(définie dans wp-config.php)" disabled>
								<p class="description">Valeur imposée par la constante <code>NA_TURNSTILE_SECRET</code>. Pour la modifier, éditez <code>wp-config.php</code>.</p>
							<?php else : ?>
								<input type="password" id="na_secret" class="regular-text" autocomplete="new-password"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[turnstile_secret]"
									value="" placeholder="<?php echo $has_secret ? '••••••••••••  (clé enregistrée)' : 'aucune clé enregistrée'; ?>">
								<p class="description">
									<?php if ( $has_secret ) : ?>
										Une clé est enregistrée. Laissez ce champ vide pour la conserver, ou saisissez-en une nouvelle pour la remplacer.
									<?php else : ?>
										Collez ici la clé secrète fournie par Cloudflare.
									<?php endif; ?>
								</p>
								<?php if ( $has_secret ) : ?>
									<p>
										<label>
											<input type="checkbox" value="1"
												name="<?php echo esc_attr( self::OPTION_NAME ); ?>[clear_secret]">
											Effacer la clé enregistrée
										</label>
									</p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2>Destinataire</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="na_email">Adresse de la rédaction</label></th>
						<td>
							<input type="email" id="na_email" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[dest_email]"
								value="<?php echo esc_attr( isset( $options['dest_email'] ) ? $options['dest_email'] : self::get( 'dest_email' ) ); ?>"
								<?php disabled( $email_locked ); ?>>
							<p class="description">
								<?php if ( $email_locked ) : ?>
									Valeur imposée par la constante <code>NA_DEST_EMAIL</code>.
								<?php else : ?>
									<?php $adresse_en_cours = trim( self::get( 'dest_email' ) ); ?>
									Adresse qui reçoit les notices et le fichier joint.
									<?php if ( '' !== $adresse_en_cours ) : ?>
										Valeur actuellement utilisée : <code><?php echo esc_html( $adresse_en_cours ); ?></code>
									<?php else : ?>
										<strong>Aucune adresse n’est renseignée</strong> : les notices déposées sont conservées, mais aucune ne peut être expédiée.
									<?php endif; ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<h2>Rythme d'envoi</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Expédition des courriels</th>
						<td>
							<?php $mode = self::get( 'mode_envoi' ); $mode_locked = self::is_locked( 'mode_envoi' ); ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
									value="differe" <?php checked( 'immediat' !== $mode ); ?>
									<?php disabled( $mode_locked ); ?>>
								Différée <strong>(recommandé)</strong> — le formulaire rend la main aussitôt
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mode_envoi]"
									value="immediat" <?php checked( 'immediat' === $mode ); ?>
									<?php disabled( $mode_locked ); ?>>
								Immédiate — le formulaire attend que le courriel soit parti
							</label>
							<p class="description">
								<?php if ( $mode_locked ) : ?>
									Valeur imposée par la constante <code>NA_MODE_ENVOI</code>.
								<?php else : ?>
									En différé, la notice est inscrite puis expédiée par le planificateur de
									WordPress&nbsp;: plusieurs personnes peuvent déposer en même temps sans que
									le site ralentisse, et un courriel qui échoue est represté tout seul. Ne
									passer en immédiat que si le planificateur est désactivé sur cet
									hébergement et qu'aucune tâche système ne le remplace — la liste
									«&nbsp;Notices Archéomed&nbsp;» le dira en s'allongeant.
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Enregistrer les réglages' ); ?>
			</form>

			<hr>

			<h2>Vérification</h2>
			<p>Ce test interroge Cloudflare pour savoir si la clé secrète enregistrée est reconnue. Il n'envoie aucune notice.</p>
			<form method="post">
				<?php wp_nonce_field( 'na_test_turnstile' ); ?>
				<?php submit_button( 'Tester la clé secrète', 'secondary', 'na_test_turnstile', false ); ?>
			</form>

			<hr>

			<h2>Mise en page</h2>
			<?php
			$modele = plugin_dir_path( __FILE__ ) . 'modele-metopes.rtf';
			if ( file_exists( $modele ) ) {
				echo '<p>Modèle de styles Métopes : <strong>présent</strong> (' . esc_html( size_format( filesize( $modele ) ) ) . ').</p>';
			} else {
				echo '<p style="color:#b32d2e;"><strong>Le fichier <code>modele-metopes.rtf</code> est introuvable.</strong> Les notices continueront d\'être transmises par courriel, mais sans la pièce jointe RTF.</p>';
			}
			?>
			<p>Pour appliquer une nouvelle feuille de styles, remplacez <code>modele-metopes.rtf</code> dans le dossier du plugin par un document enregistré au format RTF depuis le gabarit Métopes à jour. Les styles sont reconnus par leur nom : aucune modification du code n'est nécessaire.</p>

			<h2>Utilisation</h2>
			<p>Insérez le code court <code>[notice_archeomed_pactols]</code> dans la page devant accueillir le formulaire.</p>
		</div>
		<?php
	}
}
