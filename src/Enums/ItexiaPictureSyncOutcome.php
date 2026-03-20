<?php

namespace Hwkdo\IntranetAppAssets\Enums;

enum ItexiaPictureSyncOutcome
{
    /** Bild und ggf. Thumbnail aus Seventhings übernommen. */
    case PulledFromItexia;
    /** Lokales Bild nach Itexia hochgeladen und verknüpft. */
    case PushedToItexia;
    /** Lokal und Itexia haben bereits ein Bild. */
    case SkippedBothHaveImage;
    /** API-Datensatz ohne picture-Eintrag. */
    case SkippedNoPictureInApi;
    /** Lokal und API haben kein Bild. */
    case SkippedBothMissingImage;
    /** Download oder Speichern fehlgeschlagen. */
    case Failed;
}
