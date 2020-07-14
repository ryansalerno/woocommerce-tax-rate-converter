<?php

$maxed = ini_set( 'max_file_uploads', '52' );
if ( $maxed === false ) {
	$notice = 'Only ' . ini_get( 'max_file_uploads' ) . ' CSVs uploadable at once.';
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_FILES['avalara-csv'] ) ) {
	$notice = process_csv();
}

?><!doctype html>
<html lang="en-US">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>🏭 WooCommerce Tax Rate Converter 🏭</title>
	<link rel="stylesheet" href="style.css" type="text/css" media="all">
</head>
<body>
	<header>
		<h1>WooCommerce Avalara Tax Rate Converter</h1>
		<p>Combine and reformat Avalara's free tax rate CSVs into a single file you can import into WooCommerce.</p>
		<ol>
			<li><a href="https://www.avalara.com/taxrates/en/download-tax-tables.html">Get your CSVs here</a> (they ask for an email and phone number)</li>
			<li>Select them for upload below</li>
			<li>(Optionally configure a Tax Name pattern)</li>
			<li>Click Go and download the importable CSV</li>
			<li>Import into WooCommerce: <code>WooCommerce > Settings > Tax > Standard rates > Import CSV</code></li>
			<li>Profit?</li>
		</ol>
		<p>WooCommerce always appends when you upload, so if you have existing rates that you're replacing you can wipe them all out at with one click: <code>WooCommerce > Status > Tools > Delete WooCommerce tax rates</code></p>
	</header>
	<main>
		<form enctype="multipart/form-data" method="POST">
			<?php if ( isset( $notice ) ) { notice( $notice ); } ?>
			<label>Upload Avalara Files
				<input type="hidden" name="MAX_FILE_SIZE" value="512000"/>
				<input type="file" name="avalara-csv[]" accept=".csv" multiple required/>
			</label>
			<label>Tax Name Pattern
				<select name="name-pattern">
					<option value="default">&ldquo;Sales Tax&rdquo;</option>
					<option value="parens">Sales Tax (NY)</option>
					<option value="prefixed">NY Sales Tax</option>
					<option value="zip">Sales Tax: 90210</option>
				</select>
			</label>
			<button type="submit">Go</button>
		</form>
	</main>
	<footer>
		<div>
			<a href="https://github.com/ryansalerno/woocommerce-tax-rate-converter">Source on Github</a>
		</div>
	</footer>
</body>
</html><?php

function process_csv() {
	if ( array_filter( $_FILES['avalara-csv']['error'] ) ) {
		return 'There was an error with your upload.';
	}

	$sneaky = array_diff( $_FILES['avalara-csv']['type'], array('text/csv') );
	if ( $sneaky ) {
		foreach ( array_keys( $sneaky ) as $bad_key ) {
			unset( $_FILES['avalara-csv']['tmp_name'][$bad_key] );
		}
	}

	$rows = array();
	foreach ( $_FILES['avalara-csv']['tmp_name'] as $file ) {
		$rows = array_merge( $rows, parse_file( $file ) );
	}

	$converted = translate( $rows );

	if ( $converted ) {
		stream( $converted );
	} else {
		return 'Problem converting data.';
	}
}

function parse_file( $pointer ) {
	$parsed = array();

	if ( ( $handle = fopen($pointer, 'r') ) !== false ) {
		$headers = fgetcsv( $handle );

		if ( ! is_avalara_csv( $headers ) ) { return $parsed; }

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$parsed[] = array_combine( $headers, $data );
		}
		fclose( $handle );
	}

	return $parsed;
}

function is_avalara_csv( $h ) {
	$required_keys = array( 'State', 'ZipCode', 'EstimatedCombinedRate' );

	return empty( array_diff( $required_keys, $h ) );
}

function translate( $array ) {
	$headers = array( 'Country code', 'State code', 'Postcode / ZIP', 'City', 'Rate %', 'Tax name', 'Priority', 'Compound', 'Shipping', 'Tax class' );

	$rows = array();
	foreach ( $array as $row ) {
		$rows[] = array(
			'US',
			$row['State'],
			$row['ZipCode'],
			'',
			$row['EstimatedCombinedRate'] * 100,
			tax_name( $row ),
			1,
			0,
			0,
			'',
		);
	}

	return $rows ? array_merge( array( $headers ), $rows ) : $rows;
}

function tax_name( $data ) {
	$name = 'Sales Tax';

	if ( isset( $_POST['name-pattern'] ) && $_POST['name-pattern'] !== 'default' ) {
		switch ( $_POST['name-pattern'] ) {
			case 'parens':
				if ( ! empty( $data['State'] ) ) {
					$name .= ' (' . $data['State'] . ')';
				}
				break;

			case 'prefixed':
				if ( ! empty( $data['State'] ) ) {
					$name = $data['State'] . ' ' . $name;
				}
				break;

			case 'zip':
				if ( ! empty( $data['ZipCode'] ) ) {
					$name .= ': ' . $data['ZipCode'];
				}
				break;
		}
	}

	return $name;
}

// https://stackoverflow.com/a/36559772
function stream( $csv ) {
	$fh = fopen('php://output', 'w');

	ob_start();

	foreach ( $csv as $row ) {
		fputcsv( $fh, $row );
	}

	$output = ob_get_clean();

	header( 'Pragma: public' );
	header( 'Expires: 0' );
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	header( 'Cache-Control: private', false );
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="woocommerce-tax-rates-' . date('Ymd') .  '.csv";' );
	header( 'Content-Transfer-Encoding: binary' );

	exit($output);
}

function notice( $msg ) {
	echo '<aside>' . $msg . '</aside>';
}

function debug() {
	$debuggables = func_get_args();
	foreach ( $debuggables as $foo ) {
		echo '<pre class="debug">'; var_dump( $foo ); echo '</pre>';
	}
}
