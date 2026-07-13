<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\data\ActiveDataProvider;
use app\models\Conversation;
use app\models\Message;
use app\models\Advertisement;

class MessagesController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Список всех диалогов пользователя
     */
    public function actionIndex()
    {
        $userId = Yii::$app->user->id;
        
        $dataProvider = new ActiveDataProvider([
            'query' => Conversation::find()
                ->where(['is_active' => true])
                ->andWhere([
                    'or',
                    ['user1_id' => $userId],
                    ['user2_id' => $userId],
                ])
                ->with(['lastMessage', 'user1', 'user2', 'advertisement']),
            'sort' => [
                'defaultOrder' => ['last_message_at' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
    
    /**
     * Просмотр диалога
     */
    public function actionView($id)
    {
        $conversation = $this->findConversation($id);
        $userId = Yii::$app->user->id;
        
        // Проверяем, что пользователь участвует в диалоге
        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            throw new ForbiddenHttpException('У вас нет доступа к этому диалогу');
        }
        
        // Отмечаем все сообщения как прочитанные
        $conversation->markAsRead($userId);
        
        // Загружаем сообщения
        $messages = $conversation->messages;
        
        // Создаем новое сообщение
        $newMessage = new Message();
        $newMessage->conversation_id = $conversation->id;
        $newMessage->sender_id = $userId;
        $newMessage->receiver_id = $conversation->getOtherUserId($userId);
        
        if ($newMessage->load(Yii::$app->request->post()) && $newMessage->save()) {
            Yii::$app->session->setFlash('success', 'Сообщение отправлено');
            return $this->redirect(['view', 'id' => $conversation->id]);
        }
        
        $otherUser = $conversation->getOtherUser($userId);
        
        return $this->render('view', [
            'conversation' => $conversation,
            'messages' => $messages,
            'newMessage' => $newMessage,
            'otherUser' => $otherUser,
        ]);
    }
    
    /**
     * Старт диалога по объявлению
     */
    public function actionStart($advertisementId, $userId = null)
    {
        $advertisement = Advertisement::findOne($advertisementId);
        if (!$advertisement) {
            throw new NotFoundHttpException('Объявление не найдено');
        }
        
        $currentUserId = Yii::$app->user->id;
        
        // Если не указан получатель, берем автора объявления
        if ($userId === null) {
            $userId = $advertisement->user_id;
        }
        
        // Нельзя создать диалог с самим собой
        if ($currentUserId == $userId) {
            Yii::$app->session->setFlash('warning', 'Вы не можете начать диалог с самим собой');
            return $this->redirect(['advertisements/view', 'id' => $advertisementId]);
        }
        
        // Находим или создаем диалог
        $conversation = Conversation::findOrCreate($advertisementId, $currentUserId, $userId);
        
        if ($conversation) {
            return $this->redirect(['view', 'id' => $conversation->id]);
        }
        
        Yii::$app->session->setFlash('error', 'Не удалось создать диалог');
        return $this->redirect(['advertisements/view', 'id' => $advertisementId]);
    }
    
    /**
     * AJAX отправка сообщения
     */
    public function actionSendAjax()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $conversationId = Yii::$app->request->post('conversation_id');
        $messageText = Yii::$app->request->post('message');
        
        if (!$conversationId || empty(trim($messageText))) {
            return ['success' => false, 'error' => 'Сообщение не может быть пустым'];
        }
        
        $conversation = $this->findConversation($conversationId);
        $userId = Yii::$app->user->id;
        
        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            return ['success' => false, 'error' => 'У вас нет доступа к этому диалогу'];
        }
        
        $message = new Message();
        $message->conversation_id = $conversation->id;
        $message->sender_id = $userId;
        $message->receiver_id = $conversation->getOtherUserId($userId);
        $message->message = trim($messageText);
        
        if ($message->save()) {
            return [
                'success' => true,
                'message' => $this->renderPartial('_message', ['message' => $message]),
                'messageId' => $message->id,
                'createdAt' => Yii::$app->formatter->asDatetime($message->created_at),
            ];
        }
        
        return ['success' => false, 'error' => 'Не удалось отправить сообщение'];
    }
    
    /**
     * AJAX получение новых сообщений
     */
    public function actionGetNewMessages()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $conversationId = Yii::$app->request->post('conversation_id');
        $lastMessageId = Yii::$app->request->post('last_message_id', 0);
        
        if (!$conversationId) {
            return ['success' => false, 'error' => 'Не указан диалог'];
        }
        
        $conversation = $this->findConversation($conversationId);
        $userId = Yii::$app->user->id;
        
        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            return ['success' => false, 'error' => 'У вас нет доступа к этому диалогу'];
        }
        
        $messages = Message::find()
            ->where([
                'conversation_id' => $conversationId,
                'is_read' => 0,
                'receiver_id' => $userId,
            ])
            ->andWhere(['>', 'id', $lastMessageId])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();
        
        $html = '';
        foreach ($messages as $message) {
            $html .= $this->renderPartial('_message', ['message' => $message]);
        }
        
        // Отмечаем как прочитанные
        if (!empty($messages)) {
            $conversation->markAsRead($userId);
        }
        
        return [
            'success' => true,
            'html' => $html,
            'count' => count($messages),
        ];
    }
    
    /**
     * Получение количества непрочитанных сообщений
     */
    public function actionGetUnreadCount()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $userId = Yii::$app->user->id;
        
        $count = Message::find()
            ->where([
                'receiver_id' => $userId,
                'is_read' => 0,
            ])
            ->count();
        
        return ['count' => $count];
    }
    
    /**
     * Закрытие диалога
     */
    public function actionClose($id)
    {
        $conversation = $this->findConversation($id);
        $userId = Yii::$app->user->id;
        
        if ($conversation->user1_id != $userId && $conversation->user2_id != $userId) {
            throw new ForbiddenHttpException('У вас нет доступа к этому диалогу');
        }
        
        $conversation->is_active = false;
        $conversation->save();
        
        Yii::$app->session->setFlash('success', 'Диалог закрыт');
        return $this->redirect(['index']);
    }
    
    protected function findConversation($id)
    {
        $conversation = Conversation::findOne($id);
        if (!$conversation) {
            throw new NotFoundHttpException('Диалог не найден');
        }
        return $conversation;
    }
}