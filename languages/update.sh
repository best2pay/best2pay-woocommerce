#!/bin/sh

xgettext ../best2pay-payment_method.php --keyword=__
msgmerge -N best2pay-payment_method-ru_RU.po messages.po >best2pay-payment_method-ru_RU.po.new
rm messages.po
