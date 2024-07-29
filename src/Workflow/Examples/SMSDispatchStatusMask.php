<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;

/**
 * SMS DispatchStatus BitMask
 * Bitmask is 2**(DispatchStatus-1)
 */
class SMSDispatchStatusMask extends Enumeration
{
    public const Received = 0; // implied transition to start state

    public const Accepted = 1;

    public const Known = 2;

    public const UnKnown = 4;

    public const Service = 8;

    public const Command = 16;

    public const Acknowledge = 32;

    public const Acknowledged = 64;

    public const ServicePing = 128;

    public const Retry = 256;

    public const Dispatched = 512;

    public const _ErrorStatus = 32768; // Less than this is not unsuccessful - maybe incomplete
    //Unsuccessful transitions
    public const InvalidMessage = 65536;

    public const APIFailed = 131072;

    public const DispatchFailed = 262144;

    public const Banned = 524288;

    public const BannedSelf = 1048576;

    public const Spam = 2097152;

    public const Expired = 4194304;

    public const Undeliverable = 8388608;

    public const InvalidNumber = 16777216;

    public const UnProcessable = 33554432;

    public const Concede = 67108864;

    public const Abandon = 134217728;

    public const Unsuccessful = self::InvalidMessage
                       | self::APIFailed
                       | self::DispatchFailed
                       | self::Banned
                       | self::BannedSelf
                       | self::Spam
                       | self::Expired
                       | self::Undeliverable
                       | self::InvalidNumber
                       | self::UnProcessable
                       | self::Concede
                       | self::Abandon;
}
