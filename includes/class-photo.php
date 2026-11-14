<?php
defined( 'ABSPATH' ) || exit;

/** Photo upload: validate, re-encode to WebP (drops EXIF/GPS), store under uploads/rcom/ with a random name. */
class RCOM_Photo {

	const MAX_BYTES  = 8388608; // 8 MB
	const MAX_SIDE   = 1600;
	const MAX_PIXELS = 40000000;

	public static function dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . 'rcom';
	}

	public static function url( $file ) {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['baseurl'] ) . 'rcom/' . rawurlencode( $file );
	}

	public static function ensure_dir() {
		$d = self::dir();
		if ( ! wp_mkdir_p( $d ) ) {
			return;
		}
		if ( ! file_exists( $d . '/index.php' ) ) {
			file_put_contents( $d . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $d . '/.htaccess' ) ) {
			file_put_contents( $d . '/.htaccess', "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php|phtml|php[0-9]|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nRemoveHandler .php .phtml\nAddType text/plain .php .phtml\n" );
		}
	}

	/**
	 * @param array $f One entry of $_FILES.
	 * @return string|WP_Error Stored file name.
	 */
	public static function process( array $f ) {
		$bad = new WP_Error( 'rcom_photo', __( 'This photo could not be used. Please choose a JPG, PNG or WebP image up to 8 MB.', 'recipe-community' ) );
		if ( ( $f['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return $bad;
		}
		$tmp = $f['tmp_name'];
		if ( filesize( $tmp ) > self::MAX_BYTES || filesize( $tmp ) < 100 ) {
			return $bad;
		}
		// Real type from the content, never from the extension or the browser.
		$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $tmp );
		$map  = array( 'image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP );
		$info = isset( $map[ $mime ] ) ? @getimagesize( $tmp ) : false;
		if ( ! $info || $info[2] !== $map[ $mime ] || $info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000 || $info[0] * $info[1] > self::MAX_PIXELS ) {
			return $bad;
		}
		wp_raise_memory_limit( 'image' );
		$ed = wp_get_image_editor( $tmp, array( 'mime_type' => $mime ) );
		if ( is_wp_error( $ed ) ) {
			return $bad;
		}
		if ( method_exists( $ed, 'maybe_exif_rotate' ) ) {
			$ed->maybe_exif_rotate();
		}
		if ( max( $info[0], $info[1] ) > self::MAX_SIDE && is_wp_error( $ed->resize( self::MAX_SIDE, self::MAX_SIDE, false ) ) ) {
			return $bad;
		}
		$ed->set_quality( 80 );
		self::ensure_dir();
		$name = bin2hex( random_bytes( 16 ) ) . '.webp';
		$path = self::dir() . '/' . $name;
		$res  = $ed->save( $path, 'image/webp' );
		if ( is_wp_error( $res ) || ! file_exists( $res['path'] ?? $path ) ) {
			return $bad;
		}
		if ( $res['path'] !== $path ) {
			rename( $res['path'], $path );
		}
		if ( ( new finfo( FILEINFO_MIME_TYPE ) )->file( $path ) !== 'image/webp' ) {
			unlink( $path );
			return $bad;
		}
		self::strip_webp_meta( $path ); // safety net: depending on the engine the editor may keep metadata
		return $name;
	}

	/** Remove EXIF and XMP chunks from a WebP RIFF container. */
	public static function strip_webp_meta( $path ) {
		$b = file_get_contents( $path );
		if ( false === $b || strlen( $b ) < 20 || 'RIFF' !== substr( $b, 0, 4 ) || 'WEBP' !== substr( $b, 8, 4 ) ) {
			return;
		}
		$out = '';
		$p   = 12;
		$len = strlen( $b );
		while ( $p + 8 <= $len ) {
			$id    = substr( $b, $p, 4 );
			$size  = unpack( 'V', substr( $b, $p + 4, 4 ) )[1];
			$end   = min( $len, $p + 8 + $size + ( $size & 1 ) );
			$chunk = substr( $b, $p, $end - $p );
			if ( 'EXIF' === $id || 'XMP ' === $id ) {
				$chunk = '';
			} elseif ( 'VP8X' === $id ) {
				$chunk[8] = chr( ord( $chunk[8] ) & ~0x0C );
			}
			$out .= $chunk;
			$p    = $end;
		}
		$out = 'WEBP' . $out;
		file_put_contents( $path, 'RIFF' . pack( 'V', strlen( $out ) ) . $out );
	}

	public static function remove( $file ) {
		if ( $file && preg_match( '/^[a-f0-9]{32}\.webp$/', $file ) ) {
			wp_delete_file( self::dir() . '/' . $file );
		}
	}
}
