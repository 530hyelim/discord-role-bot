import { getCorrectAnswer } from './question.js';

export default {
    name: 'hint',
    description: '힌트 제공',
    async execute(message, args) {
        const correctAnswer = getCorrectAnswer();
        if (!correctAnswer) return;
        return message.reply(correctAnswer.answer.replace(/[0-9가-힣A-Za-z]/g, '●'));
    }
};
