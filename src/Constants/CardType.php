<?php

namespace Omnipay\Sipay\Constants;

/**
 * The `card_type` a POS row is returned under.
 *
 * Note that this is the funding type, not the scheme: the scheme (VISA,
 * MASTER_CARD) is carried separately in `card_scheme`.
 */
class CardType
{
    public const CREDIT_CARD = 'CREDIT_CARD';

    public const DEBIT_CARD = 'DEBIT_CARD';
}
