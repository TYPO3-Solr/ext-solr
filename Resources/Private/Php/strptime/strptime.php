<?php
/*
 * This work of Lionel SAURON (http://sauron.lionel.free.fr:80) is licensed under the
 * Creative Commons Attribution 2.0 France License.
 *
 * To view a copy of this license, visit http://creativecommons.org/licenses/by/2.0/fr/
 * or send a letter to Creative Commons, 171 Second Street, Suite 300, San Francisco, California, 94105, USA.
 */

if (!function_exists('strptime')) {

    /**
     * Parse a time/date generated with strftime().
     *
     * This function is the same as the original one defined by PHP (Linux/Unix only),
     *  but now you can use it on Windows too.
     *  Limitation : Only this format can be parsed %S, %M, %H, %d, %m, %Y
     *
     * @author Lionel SAURON
     * @version 1.0
     * @public
     *
     * @param $sDate (string)    The string to parse (e.g. returned from strftime()).
     * @param $sFormat (string)  The format used in date  (e.g. the same as used in strftime()).
     * @return (array)          Returns an array with the <code>$sDate</code> parsed, or <code>false</code> on error.
     */
    function strptime($sDate, $sFormat)
    {
        $aResult = array(
            'tm_sec' => 0,
            'tm_min' => 0,
            'tm_hour' => 0,
            'tm_mday' => 1,
            'tm_mon' => 0,
            'tm_year' => 0,
            'tm_wday' => 0,
            'tm_yday' => 0,
            'unparsed' => $sDate,
        );

        while ($sFormat != "") {
            // ===== Search a %x element, Check the static string before the %x =====
            $nIdxFound = strpos($sFormat, '%');
            if ($nIdxFound === false) {

                // There is no more format. Check the last static string.
                $aResult['unparsed'] = ($sFormat == $sDate) ? "" : $sDate;
                break;
            }

            $sFormatBefore = substr($sFormat, 0, $nIdxFound);
            $sDateBefore = substr($sDate, 0, $nIdxFound);

            if ($sFormatBefore != $sDateBefore) {
                break;
            }

            // ===== Read the value of the %x found =====
            $sFormat = substr($sFormat, $nIdxFound);
            $sDate = substr($sDate, $nIdxFound);

            $aResult['unparsed'] = $sDate;

            $sFormatCurrent = substr($sFormat, 0, 2);
            $sFormatAfter = substr($sFormat, 2);

            $nValue = -1;
            $sDateAfter = "";

            switch ($sFormatCurrent) {
                case '%S': // Seconds after the minute (0-59)

                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);

                    if (($nValue < 0) || ($nValue > 59)) {
                        return false;
                    }

                    $aResult['tm_sec'] = $nValue;
                    break;

                // ----------
                case '%M': // Minutes after the hour (0-59)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);

                    if (($nValue < 0) || ($nValue > 59)) {
                        return false;
                    }

                    $aResult['tm_min'] = $nValue;
                    break;

                // ----------
                case '%H': // Hour since midnight (0-23)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);

                    if (($nValue < 0) || ($nValue > 23)) {
                        return false;
                    }

                    $aResult['tm_hour'] = $nValue;
                    break;

                // ----------
                case '%d': // Day of the month (1-31)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);

                    if (($nValue < 1) || ($nValue > 31)) {
                        return false;
                    }

                    $aResult['tm_mday'] = $nValue;
                    break;

                // ----------
                case '%m': // Months since January (0-11)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);

                    if (($nValue < 1) || ($nValue > 12)) {
                        return false;
                    }

                    $aResult['tm_mon'] = ($nValue - 1);
                    break;

                // ----------
                case '%Y': // Years since 1900
                    sscanf($sDate, "%4d%[^\\n]", $nValue, $sDateAfter);

                    if ($nValue < 1900) {
                        return false;
                    }

                    $aResult['tm_year'] = ($nValue - 1900);
                    break;

                // ----------
                default:
                    break 2; // Break Switch and while

            } // END of case format

            // ===== Next please =====
            $sFormat = $sFormatAfter;
            $sDate = $sDateAfter;

            $aResult['unparsed'] = $sDate;
        } // END of while($sFormat != "")

        // ===== Create the other value of the result array =====
        $nParsedDateTimestamp = mktime($aResult['tm_hour'], $aResult['tm_min'],
            $aResult['tm_sec'],
            $aResult['tm_mon'] + 1, $aResult['tm_mday'],
            $aResult['tm_year'] + 1900);

        // Before PHP 5.1 return -1 when error
        if (($nParsedDateTimestamp === false)
            || ($nParsedDateTimestamp === -1)
        ) {
            return false;
        }

        $aResult['tm_wday'] = (int)strftime("%w",
            $nParsedDateTimestamp); // Days since Sunday (0-6)
        $aResult['tm_yday'] = (strftime("%j",
                $nParsedDateTimestamp) - 1); // Days since January 1 (0-365)

        return $aResult;
    } // END of function
}
