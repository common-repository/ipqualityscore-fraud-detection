<?php
function FindRiskScore($results){
	$peak = 0;
	foreach($results as $result){
		if(isset($result['Cache']['fraud_score'], $result['DataType']) && $result['DataType'] === 'device_order'){
			if($peak < $result['Cache']['fraud_score']){
				$peak = $result['Cache']['fraud_score'];
			}
		}
	}
	
	foreach($results as $result){
		if(isset($result['Cache']['transaction_details']['risk_score'])){
			if($result['Cache']['transaction_details']['risk_score'] >= $peak){
				return (int) $result['Cache']['transaction_details']['risk_score'];
			}
		}
	}
	
	return (int) $peak;
}
?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
	google.charts.load('current', {'packages':['gauge']});
	google.charts.setOnLoadCallback(drawChart);

	function drawChart() {

		var data = google.visualization.arrayToDataTable([
			['Label', 'Value'],
			['Risk Score', <?php echo FindRiskScore($results); ?>],
		]);

		var options = {
			width: 400, height: 120,
			redFrom: 85, redTo: 100,
			yellowFrom:51, yellowTo: 84,
			greenFrom:0, greenTo: 50,
			minorTicks: 5
		};

		var chart = new google.visualization.Gauge(document.getElementById('chart_div'));

		chart.draw(data, options);
	}
</script>
<div id="chart_div" style="width: 400px; height: 120px;padding-left:25%;"></div>

<?php
	$IPQS_upgrade = false;
	$timed_out = false;
	if(count($results) > 0){
		?>
		<table width="100%">
			<thead>
				<tr>
					<td><strong>Fraud API</strong></td>
					<td><strong>Result</strong></td>
					<td><strong>Score</strong></td>
				</tr>
			</thead>
			<?php
				foreach($results as $row){
					?>
					<tr>
						<td>
							<?php
								if($row['DataType'] === 'proxy_order'){
									echo 'IP &amp; Payment Data';
								}

								if($row['DataType'] === 'email_order'){
									echo 'Email Verification';
								}

								if($row['DataType'] === 'device_order'){
									echo 'Device Fingerprint';
								}
							?>
						</td>
						<td>
							<?php
								if($row['DataType'] === 'email_order'){
									if($row['Cache']['recent_abuse'] === true || $row['Cache']['disposable'] === true){
										echo '<span class="ipq_clean">Fraudulent</span>';
									} elseif($row['Cache']['valid'] === true && $row['Cache']['disposable'] === false){
										echo '<span class="ipq_clean">Clean</span>';
									} elseif($row['Cache']['timed_out'] === true && $row['Cache']['valid'] === false && $row['Cache']['dns_valid'] === true){
										echo '<span class="ipq_clean">Suspicious</span>';
									} else {
										echo '<span class="ipq_fraudulent">Invalid</span>';
									}
								}

								if($row['DataType'] === 'proxy_order'){
									$cache = $this->ConvertCacheObject([], $row['Cache']);
									if($cache['risk_score'] !== null){
										if($cache['risk_score']['risk_score'] <= 39){
											echo '<span class="ipq_clean">Clean</span>';
										}

										if($cache['risk_score'] >= 40 && $cache['risk_score'] < 70){
											echo '<span class="ipq_suspicious">Suspicious</span>';
										}

										if($cache['risk_score'] >= 70 && $cache['risk_score'] < 90){
											echo '<span class="ipq_fraudulent">High Risk</span>';
										}

										if($cache['risk_score'] >= 90 ){
											echo '<span class="ipq_fraudulent">Fraudulent</span>';
										}
									} elseif($cache['fraud_score'] !== null){
										if($cache['fraud_score'] <= 39){
											echo '<span class="ipq_clean">Clean</span>';
										}

										if($cache['fraud_score'] >= 40 && $cache['fraud_score'] < 70){
											echo '<span class="ipq_suspicious">Suspicious</span>';
										}

										if($cache['fraud_score'] >= 70 && $cache['fraud_score'] < 90){
											echo '<span class="ipq_fraudulent">High Risk</span>';
										}

										if($cache['fraud_score'] >= 90 ){
											echo '<span class="ipq_fraudulent">Fraudulent</span>';
										}
									}
								}
								
								if($row['DataType'] === 'device_order'){
									if($row['Cache']['fraud_score'] === 'N/A'){
										$timed_out = true;
										echo '<span class="ipq_fraudulent">Timed Out!</span>';
									} else {
										if($row['Cache']['fraud_score'] <= 39){
											echo '<span class="ipq_clean">Clean</span>';
										}

										if($row['Cache']['fraud_score'] >= 40 && $row['Cache']['fraud_score'] < 70){
											echo '<span class="ipq_suspicious">Suspicious</span>';
										}

										if($row['Cache']['fraud_score'] >= 70 && $row['Cache']['fraud_score'] < 90){
											echo '<span class="ipq_fraudulent">High Risk</span>';
										}

										if($row['Cache']['fraud_score'] >= 90 ){
											echo '<span class="ipq_fraudulent">Fraudulent</span>';
										}
									}
								}
							?>
						</td>
						<td>
							<?php
								if($row['DataType'] === 'email_order'){
									echo $row['Cache']['overall_score'].'/4';
								}

								if($row['DataType'] === 'proxy_order'){
									$cache = $this->ConvertCacheObject([], $row['Cache']);
									if(isset($cache['risk_score'])){
										echo $cache['risk_score'];
									} else {
										echo $cache['fraud_score'];
									}
								}
								
								if($row['DataType'] === 'device_order'){
									echo $row['Cache']['fraud_score'];
								}
							?>
						</td>
					</tr>
					<?php
					if(isset($row['Cache']['transaction_details'])){ ?>
						<tr>
							<td>Billing Phone</td>
							<td colspan="2">
								<?php
									if(isset($row['Cache']['transaction_details'])){
										if(array_key_exists('valid_billing_phone', $row['Cache']['transaction_details']) && array_key_exists('risky_billing_phone', $row['Cache']['transaction_details'])){
											if($row['Cache']['transaction_details']['risky_billing_phone'] === true){
												echo '<span class="ipq_fraudulent">High Risk</span>';
											} elseif($row['Cache']['transaction_details']['valid_billing_phone'] === true){
												echo '<span class="ipq_clean">Clean</span>';
											} elseif($row['Cache']['transaction_details']['valid_billing_phone'] !== null) {
												echo '<span class="ipq_suspicious">Invalid</span>';	
											}
											
											if($row['Cache']['transaction_details']['valid_billing_phone'] === null){
												if($row['Cache']['connection_type'] === 'Premium required.'){
													echo 'Please <a target="_blank" href="https://www.ipqualityscore.com/user/plans">upgrade your account</a> to access this data.';
													$IPQS_upgrade = true;
												} else {
													echo 'N/A';
												}
											}
										}
									}
								?>
						</tr>
						<tr>
							<td>Billing Address</td>
							<td colspan="2">
								<?php 
									if(isset($row['Cache']['transaction_details'])){
										if(array_key_exists('valid_billing_address', $row['Cache']['transaction_details'])){
											if($row['Cache']['transaction_details']['valid_billing_address'] === true){
												echo '<span class="ipq_clean">Clean</span>';
											} elseif($row['Cache']['transaction_details']['valid_billing_address'] !== null) {
												echo '<span class="ipq_suspicious">Invalid</span>';	
											}
											
											if($row['Cache']['transaction_details']['valid_billing_address'] === null){
												if($row['Cache']['connection_type'] === 'Premium required.'){
													echo 'Please <a target="_blank" href="https://www.ipqualityscore.com/user/plans">upgrade your account</a> to access this data.';
													$IPQS_upgrade = true;
												} else {
													echo 'N/A';
												}
											}
										}
									}
								?>
							</td>
						</tr>
						<?php
					}
				}
			?>
		</table>
		<br>
		<div align="center">
			<button class="button button-red report_fraudulent" type="button" data-id="<?php echo $post->ID; ?>">
				Report Fraudulent
			</button>
		</div>
		<?php if($timed_out === true){ ?>
			<p>
				This order timed out while waiting for IPQualityScore's fraud analysis. Please proceed with caution!
			</p>
		<?php } ?>
		<?php if($IPQS_upgrade === true) { ?>
			<p align="center">
				<b>Access Premium Blacklists &amp;<br>Enhanced Transaction Scoring</b><br>
				<a target="_blank" class="ipqs_upgrade_btn" href="https://www.ipqualityscore.com/user/plans">Upgrade Now</a>
			</p>
		<?php } ?>
			<p align="center">
				Scores 85+ are considered very high risk. We recommend contacting the customer to verify the order for users that fall into this threshold. Fraudulent emails indicate past abusive behavior.
			</p>
	<?php
		} else {
			?>
			<p>
				This order was not scored by IPQualityScore's fraud analysis. Please proceed with caution.
			</p>
		<?php 
		}

$report = <<<'HTML'
<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('body').on('click', '.report_fraudulent', function(){
			jQuery.ajax({
				url : '%sadmin.php?page=ipq_report_order',
				type: "POST",
				dataType : "json",
				data: 'id=' + jQuery('.report_fraudulent').attr('data-id'),
				success: function(response){
					if(response.success){
						jQuery('.report_fraudulent').hide();
						jQuery('.report_fraudulent').after('<div align="center">Thank you for your report.</div>');
					}
				}
			});
		});
	});
</script>
HTML;

echo sprintf($report, admin_url());
?>