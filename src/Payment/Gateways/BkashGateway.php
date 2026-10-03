<?php
namespace EilmoCheckout\Payment\Gateways;
defined( 'ABSPATH' ) || exit;
final class BkashGateway extends AbstractMobileGateway { protected $eilmo_brand='bKash'; public function __construct(){ $this->id=GatewaySettings::BKASH; parent::__construct(); } }
