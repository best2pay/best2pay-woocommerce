#!/bin/sh

xgettext ../best2pay-woocommerce.php --keyword=__
msgmerge -N best2pay_woocommerce-ru_RU.po messages.po >best2pay_woocommerce-ru_RU.po.new
rm messages.po
