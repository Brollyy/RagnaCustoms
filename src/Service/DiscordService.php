<?php

namespace App\Service;

use App\Entity\Song;
use App\Entity\Vote;
use App\Interface\IDiscordService;

readonly class DiscordService implements IDiscordService
{
    public function __construct(
        private string $webhookUrl,
        private string $webhookUrlUpdate,
        private string $webhookWipUrl,
        private string $webhookModerator,
        private string $webhookRequest,
        private string $webhookRanked
    ) {
    }

    public function sendWipSongMessage(Song $song)
    {
        $json_data = json_encode([
            // Message
//            "content" => "Hi vikings, there is a new map",

            // Username
            "username" => "RagnaCustoms",

            // Text-to-speech
            "tts" => false,

            // File upload
//            "file" => "",

            // Embeds Array
            "embeds" => [
                [
                    // Embed Title
                    "title" => "[W] ".$song->getName()." by ".$song->getAuthorName(),

                    // Embed Type
                    "type" => "rich",

                    // Embed Description
//                    "description" => "",

                    // URL of title link
                    "url" => "https://ragnacustoms.com/song/detail/".$song->getId(),

                    // Timestamp of embed must be formatted as ISO8601
//                    "timestamp" => $timestamp,

                    // Embed left border color in HEX
//                    "color" => "'".hexdec("3366ff")."'",


                    // Image to send
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ],

                    // Thumbnail
                    //"thumbnail" => [
                    //    "url" => "https://ru.gravatar.com/userimage/28503754/1168e2bddca84fec2a63addb348c571d.jpg?size=400"
                    //],

                    // Author
//                    "author" => [
//                        "name" => "krasin.space",
//                        "url" => "https://krasin.space/"
//                    ],

                    // Additional Fields array
                    "fields" => [
////                        // Field 1
                        [
                            "name" => "Mapper",
                            "value" => "'".$song->getLevelAuthorName()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Difficulties",
                            "value" => "'".$song->getSongDifficultiesStr()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Duration",
                            "value" => "'".$song->getApproximativeDurationMS()."'",
                            "inline" => true
                        ]
////                        // Etc..
                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookWipUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    public function sendNewSongMessage(Song $song)
    {
        $timestamp = date("c", strtotime("now"));

        $json_data = json_encode([
            // Message
//            "content" => "Hi vikings, there is a new map",

            // Username
            "username" => "RagnaCustoms",

            // Text-to-speech
            "tts" => false,

            // File upload
//            "file" => "",

            // Embeds Array
            "embeds" => [
                [
                    // Embed Title
                    "title" => $song->getName()." by ".$song->getAuthorName(),

                    // Embed Type
                    "type" => "rich",

                    // Embed Description
//                    "description" => "",

                    // URL of title link
                    "url" => "https://ragnacustoms.com/song/detail/".$song->getId(),

                    // Timestamp of embed must be formatted as ISO8601
//                    "timestamp" => $timestamp,

                    // Embed left border color in HEX
//                    "color" => "'".hexdec("3366ff")."'",


                    // Image to send
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ],

                    // Thumbnail
                    //"thumbnail" => [
                    //    "url" => "https://ru.gravatar.com/userimage/28503754/1168e2bddca84fec2a63addb348c571d.jpg?size=400"
                    //],

                    // Author
//                    "author" => [
//                        "name" => "krasin.space",
//                        "url" => "https://krasin.space/"
//                    ],

                    // Additional Fields array
                    "fields" => [
////                        // Field 1
                        [
                            "name" => "Mapper",
                            "value" => "'".$song->getLevelAuthorName()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Difficulties",
                            "value" => "'".$song->getSongDifficultiesStr()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Duration",
                            "value" => "'".$song->getApproximativeDurationMS()."'",
                            "inline" => true
                        ]
////                        // Etc..
                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    public function sendUpdatedSongMessage(Song $song)
    {
        $json_data = json_encode([
            // Message
//            "content" => "Hi vikings, there is a new map",

            // Username
            "username" => "RagnaCustoms",

            // Text-to-speech
            "tts" => false,

            // File upload
//            "file" => "",

            // Embeds Array
            "embeds" => [
                [
                    // Embed Title
                    "title" => $song->getName()." by ".$song->getAuthorName(),

                    // Embed Type
                    "type" => "rich",

                    // Embed Description
//                    "description" => "",

                    // URL of title link
                    "url" => "https://ragnacustoms.com/song/detail/".$song->getId(),

                    // Timestamp of embed must be formatted as ISO8601
//                    "timestamp" => $timestamp,

                    // Embed left border color in HEX
//                    "color" => "'".hexdec("3366ff")."'",


                    // Image to send
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ],

                    "fields" => [
                        [
                            "name" => "Mapper",
                            "value" => "'".$song->getLevelAuthorName()."'",
                            "inline" => true
                        ],
                        [
                            "name" => "Difficulties",
                            "value" => "'".$song->getSongDifficultiesStr()."'",
                            "inline" => true
                        ],
                        [
                            "name" => "Duration",
                            "value" => "'".$song->getApproximativeDurationMS()."'",
                            "inline" => true
                        ]

                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookUrlUpdate);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    public function sendFeedback(Vote $feedback)
    {
        $song = $feedback->getSong();
        $json_data = json_encode([
            "username" => "RagnaCustoms",
            "content" => "**New feedback for ".$song->getName()."**",
            "embeds" => [
                [
                    "title" => "Feedback content :",
                    "description" => ($feedback->getFeedback()),
                    "author" => [
                        "name" => $feedback->getUser()->getUsername()
                    ],
                    "url" => "https://ragnacustoms.com/admin",
                    "fields" => [
                        [
                            "name" => "Mappers",
                            "value" => $song->getMappersNames(),
                            "inline" => true
                        ]
                    ],
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookModerator);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    public function deletedSong(Song $song)
    {
        $json_data = json_encode([
            "username" => "RagnaCustoms",
            "content" => "Song Deleted : ".$song->getName(),

            "embeds" => [
                [
                    "title" => "Song Deleted :",
                    "description" => ($song->getName()),
                    "url" => "https://ragnacustoms.com/admin",
                    "fields" => [
                        [
                            "name" => "Mapper(s)",
                            "value" => $song->getMappersNames(),
                            "inline" => true
                        ]
                    ],
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ]
                ]
            ]

        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookModerator);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    public function rankedSong(Song $song)
    {
        $timestamp = date("c", strtotime("now"));

        $json_data = json_encode([
            // Message
//            "content" => "Hi vikings, there is a new map",

            // Username
            "username" => "RagnaCustoms",

            // Text-to-speech
            "tts" => false,

            // File upload
//            "file" => "",

            // Embeds Array
            "embeds" => [
                [
                    // Embed Title
                    "title" => ($song->isRanked() ? "[RANKED] " : "[UNRANKED] ").$song->getName(
                        )." by ".$song->getAuthorName(),

                    // Embed Type
                    "type" => "rich",

                    // Embed Description
//                    "description" => "",

                    // URL of title link
                    "url" => "https://ragnacustoms.com/song/detail/".$song->getId(),

                    // Timestamp of embed must be formatted as ISO8601
//                    "timestamp" => $timestamp,

                    // Embed left border color in HEX
//                    "color" => "'".hexdec("3366ff")."'",


                    // Image to send
                    "image" => [
                        "url" => "https://ragnacustoms.com".$song->getCover()
                    ],

                    // Thumbnail
                    //"thumbnail" => [
                    //    "url" => "https://ru.gravatar.com/userimage/28503754/1168e2bddca84fec2a63addb348c571d.jpg?size=400"
                    //],

                    // Author
//                    "author" => [
//                        "name" => "krasin.space",
//                        "url" => "https://krasin.space/"
//                    ],

                    // Additional Fields array
                    "fields" => [
////                        // Field 1
                        [
                            "name" => "Mapper",
                            "value" => "'".$song->getLevelAuthorName()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Difficulties",
                            "value" => "'".$song->getSongDifficultiesStr()."'",
                            "inline" => true
                        ],
////                        // Field 2
                        [
                            "name" => "Duration",
                            "value" => "'".$song->getApproximativeDurationMS()."'",
                            "inline" => true
                        ]
////                        // Etc..
                    ]
                ]
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);


        $ch = curl_init($this->webhookRanked);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
    }
}

