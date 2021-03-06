<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message_Attachment extends Model
{
	protected $table = 'messages_attachments';

    protected $fillable = ['url', 'fk_user_id', 'fk_message_id'];

    public function getCreatedAtAttribute($date)
    {
        return strtotime($date);
    }

    public function getUpdatedAtAttribute($date)
    {
        return strtotime($date);
    }
}