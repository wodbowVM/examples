<?
namespace Housage\Comments;

use \Housage\Comments\ORM\CommentsTable,
    \Housage\Comments\ORM\AnswersTable,
    \Housage\Comments\ORM\VotingTable;

if (!class_exists('Comments')) {

    class Comments {

        /**
         * Очищает кэш
         */
        static function deleteCache() {
            $siteId = SITE_ID;
            if (empty($siteId)) {
                $siteId = 's7';
            }
            BXClearCache(true, '/' . $siteId . '/housage/comments/');
        }

        /**
         * Определяет, нужна ли конвертация строки в иную кодировку
         *
         * @return string
         */
        static function needConvert() {
            $arSiteFilter = [
                'ACTIVE' => 'Y',
                'ID' => SITE_ID
            ];
            $dbSite = \CSite::GetList($by = 'ID', $order = 'ASC', $arSiteFilter);
            if ($arSite = $dbSite->Fetch()) {
                if (strstr(strtolower($arSite['CHARSET']), 'windows') || strstr($arSite['CHARSET'], '1251')) {
                    return 'Y';
                }
            }

            return 'N';
        }

        /**
         * Выбрать все комментарии к указанному URL
         *
         * @param $url
         * @param $count
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getComments($url, $count = 5, $offset = 0) {
            $count = intval($count);
            $offset = intval($offset);
            $res = CommentsTable::getList([
                'filter' => [
                    'ACTIVE' => 'Y',
                    'URL'    => $url
                ],
                'order' => ['DATE' => 'DESC'],
                'limit' => $count,
                'offset' => $offset
            ]);

            return $res;
        }

        /**
         * Возвращает кол-во комментариев по url
         *
         * @param $url
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getCountComments($url) {
            $res = CommentsTable::getList([
                'select' => array('CNT'),
                'runtime' => array(
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ),
                'filter' => [
                    'ACTIVE' => 'Y',
                    'URL'    => $url
                ]
            ]);
            $arRes = $res->fetch();

            return $arRes['CNT'];
        }

        /**
         * Выбрать комментарии к указанному элементу
         *
         * @param $elementId
         * @param $count
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getCommentsByElementId($elementId, $count = 5, $offset = 0) {
            $elementId = intval($elementId);
            $count = intval($count);
            $offset = intval($offset);
            $res = CommentsTable::getList([
                'filter' => [
                    'ACTIVE' => 'Y',
                    'ELEMENT_ID' => $elementId
                ],
                'order' => ['DATE' => 'DESC'],
                'limit' => $count,
                'offset' => $offset
            ]);

            return $res;
        }

        /**
         * Возвращает количество комментариев к элементу
         *
         * @param $elementId
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getCountCommentsByElementId($elementId) {
            $elementId = intval($elementId);
            $res = CommentsTable::getList([
                'select' => array('CNT'),
                'runtime' => array(
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ),
                'filter' => [
                    'ACTIVE' => 'Y',
                    'ELEMENT_ID' => $elementId
                ]
            ]);
            $arRes = $res->fetch();

            return $arRes['CNT'];
        }

        /**
         * Добавить комментарий
         *
         * @param $rating
         * @param $text
         * @param $url
         * @param int $userId
         * @param int $elementId
         *
         * @return \Bitrix\Main\ORM\Data\AddResult
         *
         * @throws \Exception
         */
        function addComment($rating, $text, $url, $userId, $elementId = 0) {
            if (Comments::needConvert() == 'Y') {
                $text = iconv('UTF-8', 'Windows-1251', $text);
            }

            if (\COption::GetOptionString('housage.comments', 'ENABLE_MODERATION') == 'Y') {
                $active = 'N';
            } else {
                $active = 'Y';
                self::deleteCache();
            }

            $text = nl2br($text);

            /**
             * Не восстановлена работоспособность функционала по прикреплению картинок к отзыву
             * Согласовано с руководством
             */
            $res = CommentsTable::add([
                'ACTIVE'     => $active,
                'DATE' 	     => time(),
                'RATING'     => $rating,
                'COMMENT'    => $text,
                'URL' 	     => $url,
                'ELEMENT_ID' => $elementId,
                'USER_ID'    => $userId
            ]);

            return $res;
        }

        /**
         * Редактировать комментарий
         *
         * @param $commentId
         * @param $date
         * @param $rating
         * @param $text
         * @param string $answer
         *
         * @return \Bitrix\Main\ORM\Data\UpdateResult
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function editComment($commentId, $date, $rating, $text, $answer = '') {
            if (Comments::needConvert() == 'Y') {
                $text = iconv('UTF-8', 'Windows-1251', $text);
                $answer = iconv('UTF-8', 'Windows-1251', $answer);
            }

            $arDateTime = explode(' ', $date);
            $arDate = explode('.', $arDateTime[0]);

            $timeTmp = $arDate[2] . '-' . $arDate[1] . '-' . $arDate[0] . ' ' . $arDateTime[1];

            $time = strtotime($timeTmp);
            /**
             * Не восстановлена работоспособность функционала по прикреплению картинок к отзыву
             * Согласовано с руководством
             */
            /*$imagesIds = '';
            $arImages = Array();
            $dbComment = $DB->Query('SELECT IMAGES_IDS FROM b_housage_comments WHERE ID=' . $commentId);
            if ($arComment = $dbComment->Fetch())
            {
                if ($arComment['IMAGES_IDS'])
                {
                    $arImages = explode(',', $arComment['IMAGES_IDS']);

                    $arDelete = Array();
                    foreach ($_POST as $field => $value)
                    {
                        if (strstr($field, 'COMMENT_PICTURE_DELETE'))
                        {
                            $picId = substr($field, strlen('COMMENT_PICTURE_DELETE_'));

                            if ($value == 'Y')
                            {
                                $arDelete[] = $picId;
                            }
                        }
                    }

                    if (!empty($arDelete))
                    {
                        foreach ($arImages as $k => $imageId)
                        {
                            if (in_array($imageId, $arDelete))
                            {
                                unset($arImages[$k]);
                            }
                        }
                    }
                }
            }

            for ($i = 1; $i <= 20; $i++)
            {
                if (isset($_FILES['COMMENT_PICTURE_' . $i]) && !empty($_FILES['COMMENT_PICTURE_' . $i]))
                {
                    $arFile = array_merge($_FILES['COMMENT_PICTURE_' . $i]);
                    $arFile['del'] = ${'image_del'};
                    $arFile['MODULE_ID'] = 'comments';

                    $fileId = CFile::SaveFile($arFile, 'comments_images');
                    $arImages[] = $fileId;
                }
            }

            if (!empty($arImages))
            {
                foreach ($arImages as $k => $v)
                {
                    if (!$v)
                    {
                        unset($arImages[$k]);
                    }
                }

                $imagesIds = implode(',', $arImages);
            }

            $text = str_replace('"', '\"', $text);*/

            $text = nl2br($text);
            
            $result = CommentsTable::update(
                $commentId,
                [
                    'DATE'    => $time,
                    'RATING'  => $rating,
                    'COMMENT' => $text
                ]
            );

            $rsAnswer = AnswersTable::getList([
                'filter' => [ 'COMMENT_ID' => $commentId ],
                'select' => [ 'ID' ]
            ]);

            $updated = false;
            while ($arAnswer = $rsAnswer->fetch()) {
                $updated = true;
                AnswersTable::update(
                    $arAnswer['ID'],
                    [ 'TEXT' => $answer ]
                );
            }
            if (!$updated) {
                AnswersTable::add([
                    'COMMENT_ID' => $commentId,
                    'TEXT'       => $answer
                ]);
            }

            self::deleteCache();

            return $result;
        }

        /**
         * Выслать письмо админам на почту о добавлении нового комментария
         *
         * @param $url
         * @param $rating
         * @param $text
         * @param $arSites
         */
        function sendMail($url, $rating, $text, $arSites) {
            if (Comments::needConvert() == 'Y') {
                $text = iconv('UTF-8', 'Windows-1251', $text);
            }

            $emailTo = \COption::GetOptionString('housage.comments', 'SEND_MAIL_ADDRESS');

            $arFields = [
                'URL' => 'http://' . $_SERVER['HTTP_HOST'] . $url,
                'RATING' => $rating,
                'TEXT' => $text,
                'EMAIL_TO' => $emailTo,
                'MODULE_PAGE' => 'http://' . $_SERVER['HTTP_HOST'] . '/bitrix/admin/settings.php?lang=ru&mid=housage.comments'
            ];

            CEvent::Send('HOUSAGE_COMMENT', $arSites, $arFields);
        }

        /**
         * Выбрать комментарии, требующие модерации
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getModerationComments() {
            $res = CommentsTable::getList([
                'filter' => [ 'ACTIVE' => 'N' ],
                'order'  => [ 'DATE'   => 'DESC' ]
            ]);

            return $res;
        }

        /**
         * Выбрать все комментарии
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getListComments() {
            $res = CommentsTable::getList([
                'filter' => [ 'ACTIVE' => 'Y' ],
                'order'  => [ 'DATE'   => 'DESC' ]
            ]);

            return $res;
        }

        /**
         * Активировать комментарий
         *
         * @param $commentId
         *
         * @throws \Exception
         */
        function activateComment($commentId) {
            self::deleteCache();

            CommentsTable::update(
                $commentId,
                [ 'ACTIVE' => 'Y' ]
            );
        }

        /**
         * Удалить комментарий
         * 
         * @param $commentId
         * 
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function deleteComment($commentId) {
            /**
             * Не восстановлена работоспособность функционала по прикреплению картинок к отзыву
             * Согласовано с руководством
             */
            /*$files = '';
            $dbFiles = $DB->Query('SELECT IMAGES_IDS FROM b_housage_comments WHERE ID=' . $commentId);
            if ($arFile = $dbFiles->Fetch())
            {
                $files = $arFile['IMAGES_IDS'];
            }

            if ($files)
            {
                $arFiles = explode(',', $files);

                foreach ($arFiles as $fileId)
                {
                    CFile::Delete($fileId);
                }
            }*/

            self::deleteCache();

            CommentsTable::delete($commentId);

            $rsVoting = VotingTable::getList([
                'filter' => [ 'COMMENT_ID' => $commentId ],
                'select' => [ 'ID' ]
            ]);

            while ($arVoting = $rsVoting->fetch()) {
                VotingTable::delete($arVoting['ID']);
            }
        }

        /**
         * Сохраняет настройки
         *
         * @param $showStats
         * @param $includeJquery
         * @param $sendMail
         * @param $sendMailAddress
         * @param $enableModeration
         * @param $enableImages
         * @param $onlyAuthorized
         * @param $showCommentsCount
         * @param $enableVoting
         * @param $moderationAccess
         * @param $answerTitle
         */
        function saveOptions($showStats, $includeJquery, $sendMail, $sendMailAddress, $enableModeration, $enableImages, $onlyAuthorized, $showCommentsCount, $enableVoting, $moderationAccess, $answerTitle) {
            if (strpos(SITE_CHARSET, 'windows') !== false ||  strpos(SITE_CHARSET, '1251') !== false) {
                $answerTitle = iconv('UTF-8', 'Windows-1251', $answerTitle);
            }

            if ($showStats) {
                \COption::SetOptionString('housage.comments', 'SHOW_STATS', $showStats);
            }

            if ($includeJquery) {
                \COption::SetOptionString('housage.comments', 'INCLUDE_JQUERY', $includeJquery);
            }

            if ($sendMail) {
                \COption::SetOptionString('housage.comments', 'SEND_MAIL', $sendMail);
            }

            \COption::SetOptionString('housage.comments', 'SEND_MAIL_ADDRESS', $sendMailAddress);

            if ($enableModeration) {
                \COption::SetOptionString('housage.comments', 'ENABLE_MODERATION', $enableModeration);
            }

            /**
             * Не восстановлена работоспособность функционала по прикреплению картинок к отзыву
             * Согласовано с руководством
             */
            /*if ($enableImages) {
                \COption::SetOptionString('housage.comments', 'ENABLE_IMAGES', $enableImages);
            }*/

            if ($onlyAuthorized) {
                \COption::SetOptionString('housage.comments', 'ONLY_AUTHORIZED', $onlyAuthorized);
            }

            if ($showCommentsCount) {
                \COption::SetOptionString('housage.comments', 'SHOW_COMMENTS_COUNT', $showCommentsCount);
            }

            if ($enableVoting) {
                \COption::SetOptionString('housage.comments', 'ENABLE_VOTING', $enableVoting);
            }

            \COption::SetOptionString('housage.comments', 'MODERATION_ACCESS', $moderationAccess);

            \COption::SetOptionString('housage.comments', 'ANSWER_TITLE', $answerTitle);

            self::deleteCache();
        }

        /**
         * Выбрать голосования
         *
         * @return \Bitrix\Main\ORM\Query\Result
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getVotes() {
            $arVotes = [];

            $rsVoting = VotingTable::getList([
                'select' => [
                    'COMMENT_ID',
                    'VOTE'
                ]
            ]);

            while ($arVoting = $rsVoting->fetch()) {
                switch ($arVoting['VOTE']) {
                    case 'UP':
                        $arVotes[$arVoting['COMMENT_ID']]['UP']++;
                        break;

                    case 'DOWN':
                        $arVotes[$arVoting['COMMENT_ID']]['DOWN']++;
                        break;
                }
            }

            return $arVotes;
        }

        /**
         * Добавить голосование
         *
         * @param $commentId
         * @param $vote
         *
         * @throws \Exception
         */
        function addVote($commentId, $vote) {

            self::deleteCache();

            global $APPLICATION;
            $APPLICATION->set_cookie('HOUSAGE_COMMENTS_VOTED_FOR_' . $commentId, $vote);

            VotingTable::add([
                'COMMENT_ID' => $commentId,
                'VOTE'       => $vote
            ]);
        }

        /**
         * Выбирает ответы
         *
         * @return array
         *
         * @throws \Bitrix\Main\ArgumentException
         * @throws \Bitrix\Main\ObjectPropertyException
         * @throws \Bitrix\Main\SystemException
         */
        function getAnswers() {
            $arResult = [];
            $rsAnswers = AnswersTable::getList([
                'select' => [
                    'COMMENT_ID',
                    'TEXT'
                ]
            ]);
            while ($arAnswer = $rsAnswers->fetch() ) {
                $arResult[$arAnswer['COMMENT_ID']] = $arAnswer['TEXT'];
            }

            return $arResult;
        }
    }
}