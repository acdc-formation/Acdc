#!/usr/bin/env bash
###############################################################################
# ACDC Formation SAAS — Installation d'un WordPress de TEST + bibliothèque de
# tests du cœur (pour la suite « integration »).
#
# But : monter, dans un répertoire TEMPORAIRE, un WordPress jetable et la
# bibliothèque de tests officielle (WP_UnitTestCase), puis créer une base de
# données STRICTEMENT JETABLE dédiée aux tests. AUCUNE donnée de production.
#
# Inspiré du script canonique wp-cli/scaffold, adapté pour fonctionner SANS
# Subversion (repli sur une archive GitHub de wordpress-develop).
#
# Usage :
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
# Exemple CI :
#   bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 6.6.2 true
###############################################################################

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
# Pin « courant » explicite et révisable (source : wordpress.org/download/releases).
WP_VERSION=${5-7.0.2}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -s -L "$1" > "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "Ni curl ni wget disponibles." >&2
		exit 1
	fi
}

# --- Résolution de la version WordPress -------------------------------------
if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/${WP_VERSION%\-*}"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	# Version X.Y (.0) : TAG EXACT et immuable (pas de branche mobile).
	WP_TESTS_TAG="tags/$WP_VERSION"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	# Version X.Y.Z : TAG EXACT et immuable.
	WP_TESTS_TAG="tags/$WP_VERSION"
else
	WP_TESTS_TAG="trunk"
fi

# --- Téléchargement du cœur WordPress (version épinglée) --------------------
install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		echo "WordPress déjà présent dans $WP_CORE_DIR"
		return
	fi
	mkdir -p "$WP_CORE_DIR"

	if [ "$WP_VERSION" == "latest" ]; then
		local ARCHIVE_NAME="latest"
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
	fi

	download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" "$TMPDIR/wordpress.tar.gz"
	tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"

	download "https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php" "$WP_CORE_DIR/wp-content/db.php" || true
}

# --- Téléchargement de la bibliothèque de tests ----------------------------
# Utilise Subversion si disponible ; sinon repli sur l'archive GitHub de
# WordPress/wordpress-develop pour la même version.
install_test_suite() {
	if [ -d "$WP_TESTS_DIR" ] && [ -f "$WP_TESTS_DIR/includes/functions.php" ]; then
		echo "Bibliothèque de tests déjà présente dans $WP_TESTS_DIR"
	else
		mkdir -p "$WP_TESTS_DIR"
		if command -v svn >/dev/null 2>&1; then
			svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
			svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
		else
			# Repli sans SVN : archive GitHub de wordpress-develop (tag = version).
			local REF
			if [ "$WP_VERSION" == "latest" ] || [ "$WP_TESTS_TAG" == "trunk" ]; then
				REF="trunk"
			else
				REF="$WP_VERSION"
			fi
			download "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${REF}.tar.gz" "$TMPDIR/wp-develop.tar.gz" \
				|| download "https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz" "$TMPDIR/wp-develop.tar.gz"
			mkdir -p "$TMPDIR/wp-develop"
			tar --strip-components=1 -zxmf "$TMPDIR/wp-develop.tar.gz" -C "$TMPDIR/wp-develop"
			cp -r "$TMPDIR/wp-develop/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
			cp -r "$TMPDIR/wp-develop/tests/phpunit/data" "$WP_TESTS_DIR/data"
		fi
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php" \
			|| cp "$TMPDIR/wp-develop/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

		# WP_CORE_DIR sans slash final pour le sample.
		local WP_CORE_DIR_NO_SLASH=${WP_CORE_DIR%/}
		sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_NO_SLASH/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
		rm -f "$WP_TESTS_DIR/wp-tests-config.php".bak
	fi
}

# --- Création de la base JETABLE -------------------------------------------
install_db() {
	if [ "${SKIP_DB_CREATE}" = "true" ]; then
		echo "Création de la base ignorée (SKIP_DB_CREATE=true)."
		return 0
	fi

	# Ce script ne SUPPRIME/DROP/RESET jamais aucune base : CRÉATION idempotente
	# uniquement. Garde-fou STRICT (non destructif) : refuser tout nom/hôte
	# différent des littéraux approuvés (cohérent avec le bootstrap d'intégration).
	if [ "$DB_NAME" != "wordpress_test" ] || [ "$DB_HOST" != "127.0.0.1:3306" ]; then
		echo "REFUS : DB_NAME/DB_HOST ('$DB_NAME' / '$DB_HOST') != littéraux approuvés (wordpress_test / 127.0.0.1:3306)." >&2
		exit 2
	fi

	local PARTS EXTRA
	IFS=':' read -ra PARTS <<< "$DB_HOST"
	local DB_HOSTNAME=${PARTS[0]}
	local DB_SOCK_OR_PORT=${PARTS[1]-}
	EXTRA=""
	if [ -n "$DB_HOSTNAME" ]; then
		if [ "$(echo "$DB_SOCK_OR_PORT" | grep -e '^[0-9]\{1,\}$')" != '' ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif [ -n "$DB_SOCK_OR_PORT" ]; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif [ -n "$DB_HOSTNAME" ]; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# Création NON destructive et idempotente (aucun DROP). Sûr en cas de ré-exécution.
	mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`"
}

install_wp
install_test_suite
install_db

echo "OK — WordPress de test installé (version $WP_VERSION)."
echo "WP_CORE_DIR=$WP_CORE_DIR"
echo "WP_TESTS_DIR=$WP_TESTS_DIR"
