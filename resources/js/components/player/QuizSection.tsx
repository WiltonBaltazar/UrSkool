import { AlertTriangle, CheckCircle2, ChevronLeft, ChevronRight, Trophy, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";

interface QuizQuestionState {
  id: string;
  question: string;
  options: string[];
  correctOptionIndex: number;
}

interface QuizAttemptState {
  questionOrder: string[];
  selectedAnswers: Record<string, number>;
  submitted: boolean;
  score: number;
  passed: boolean;
  correctCount: number;
  total: number;
  currentQuestionIndex: number;
  passPercentage: number;
  signature: string;
  attemptNumber: number;
}

interface QuizSectionProps {
  lessonTitle: string;
  quizPassPercentage: number;
  orderedQuizQuestions: QuizQuestionState[];
  currentQuizQuestion: QuizQuestionState | null;
  currentQuizState: QuizAttemptState | undefined;
  currentQuizQuestionAnswered: boolean;
  quizHasUnanswered: boolean;
  scoreCircleValue: number;
  onSelectOption: (questionId: string, optionIndex: number) => void;
  onPreviousQuestion: () => void;
  onNextQuestion: () => void;
  onSubmit: () => void;
}

export function QuizSection({
  lessonTitle,
  quizPassPercentage,
  orderedQuizQuestions,
  currentQuizQuestion,
  currentQuizState,
  currentQuizQuestionAnswered,
  quizHasUnanswered,
  scoreCircleValue,
  onSelectOption,
  onPreviousQuestion,
  onNextQuestion,
  onSubmit,
}: QuizSectionProps) {
  return (
    <section className="min-h-0 flex flex-col overflow-hidden rounded-lg border border-[#2a2a2a] bg-[#000000] text-[#f5f5f5]">
      <div className="px-5 py-4 border-b border-[#1f1f1f] bg-[#050505]">
        <p className="text-xs uppercase tracking-[0.18em] text-[#a3a3a3]">Questionário</p>
        <h2 className="text-2xl font-semibold mt-1">{lessonTitle}</h2>
        <p className="text-sm text-[#cfcfcf] mt-1">
          Nota mínima para avançar: <span className="font-semibold text-[#ffffff]">{quizPassPercentage}%</span>
        </p>
      </div>

      {orderedQuizQuestions.length === 0 ? (
        <div className="flex-1 p-6 flex items-center justify-center">
          <div className="rounded-lg border border-[#2f2f2f] bg-[#0c0c0c] px-5 py-4 max-w-md text-center">
            <AlertTriangle className="h-6 w-6 mx-auto text-[#ffffff]" />
            <p className="mt-3 font-medium">Questionário ainda não configurado</p>
            <p className="mt-1 text-sm text-[#b3b3b3]">O administrador precisa adicionar perguntas e respostas primeiro.</p>
          </div>
        </div>
      ) : currentQuizState?.submitted ? (
        <div className="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">
          <div className="rounded-xl border border-[#2f2f2f] bg-[#0b0b0b] p-5">
            <div className="flex flex-col md:flex-row md:items-center gap-4 md:justify-between">
              <div className="flex items-center gap-4">
                <div
                  className="h-16 w-16 rounded-full p-[3px]"
                  style={{
                    background: `conic-gradient(#ffffff ${(scoreCircleValue / 100) * 360}deg, #2a2a2a 0deg)`,
                  }}
                >
                  <div className="h-full w-full rounded-full bg-[#000000] flex items-center justify-center text-sm font-semibold">
                    {scoreCircleValue}%
                  </div>
                </div>
                <div>
                  <p className="text-sm uppercase tracking-wide text-[#c2c2c2]">Resultado</p>
                  <p className="text-xl font-semibold">
                    {currentQuizState.passed ? "Aprovado" : "Reprovado"}
                  </p>
                  <p className="text-sm text-[#c2c2c2]">
                    {currentQuizState.correctCount}/{currentQuizState.total} respostas corretas
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                {currentQuizState.passed ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-[#183b2d] px-3 py-1 text-xs font-medium text-[#90f3c4] border border-[#2d7656]">
                    <Trophy className="h-3.5 w-3.5" />
                    Aprovado
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 rounded-full bg-[#4b1d2b] px-3 py-1 text-xs font-medium text-[#ff9bb6] border border-[#7a3248]">
                    <XCircle className="h-3.5 w-3.5" />
                    Repetir exercício
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="space-y-4">
            {orderedQuizQuestions.map((question, questionIndex) => {
              const selected = currentQuizState.selectedAnswers[question.id];
              const isCorrect = selected === question.correctOptionIndex;

              return (
                <article key={question.id} className="rounded-xl border border-[#2c2c2c] bg-[#0a0a0a] p-4 space-y-3">
                  <div className="flex items-start justify-between gap-3">
                    <p className="font-medium">
                      {questionIndex + 1}. {question.question}
                    </p>
                    {isCorrect ? (
                      <CheckCircle2 className="h-5 w-5 text-[#6cf1b9] shrink-0" />
                    ) : (
                      <XCircle className="h-5 w-5 text-[#ff8fae] shrink-0" />
                    )}
                  </div>
                  <div className="space-y-2">
                    {question.options.map((option, optionIndex) => {
                      const isSelected = selected === optionIndex;
                      const isAnswer = optionIndex === question.correctOptionIndex;

                      return (
                        <div
                          key={`${question.id}-option-${optionIndex}`}
                          className={`rounded-md px-3 py-2 text-sm border ${
                            isAnswer
                              ? "bg-[#173b2e] border-[#46be86] text-[#aff7d9]"
                              : isSelected
                                ? "bg-[#422338] border-[#a34967] text-[#ffc2d4]"
                                : "bg-[#0d0d0d] border-[#2b2b2b] text-[#d6d6d6]"
                          }`}
                        >
                          {option}
                        </div>
                      );
                    })}
                  </div>
                </article>
              );
            })}
          </div>
        </div>
      ) : (
        <>
          <div className="px-5 py-3 border-b border-[#1f1f1f] bg-[#050505] space-y-2">
            <div className="flex items-center justify-between text-xs text-[#c2c2c2]">
              <span>
                Pergunta {(currentQuizState?.currentQuestionIndex || 0) + 1} de {orderedQuizQuestions.length}
              </span>
              <span>Tentativa {currentQuizState?.attemptNumber || 1}</span>
            </div>
            <Progress
              value={
                orderedQuizQuestions.length > 0
                  ? (((currentQuizState?.currentQuestionIndex || 0) + 1) / orderedQuizQuestions.length) * 100
                  : 0
              }
              className="h-2 bg-[#1f1f1f]"
            />
          </div>

          <div className="flex-1 overflow-y-auto p-5 md:p-7 space-y-6">
            {currentQuizQuestion && (
              <>
                <h3 className="text-xl md:text-2xl font-semibold leading-relaxed">{currentQuizQuestion.question}</h3>
                <div className="space-y-3">
                  {currentQuizQuestion.options.map((option, optionIndex) => {
                    const isSelected = currentQuizState?.selectedAnswers[currentQuizQuestion.id] === optionIndex;

                    return (
                      <button
                        key={`${currentQuizQuestion.id}-${optionIndex}`}
                        type="button"
                        onClick={() => onSelectOption(currentQuizQuestion.id, optionIndex)}
                        className={`w-full rounded-lg border px-4 py-3 text-left transition-colors ${
                          isSelected
                            ? "border-[#ffffff] bg-[#171717] text-[#ffffff]"
                            : "border-[#2d2d2d] bg-[#0c0c0c] text-[#ededed] hover:bg-[#1a1a1a]"
                        }`}
                      >
                        {option}
                      </button>
                    );
                  })}
                </div>
              </>
            )}
          </div>

          <div className="px-5 py-4 border-t border-[#1f1f1f] bg-[#050505] flex items-center justify-between gap-2">
            <Button
              variant="outline"
              className="border-[#4a4a4a] text-[#ffffff] bg-transparent hover:bg-[#121212]"
              disabled={(currentQuizState?.currentQuestionIndex || 0) === 0}
              onClick={onPreviousQuestion}
            >
              <ChevronLeft className="h-4 w-4 mr-1" />
              Anterior
            </Button>

            {(currentQuizState?.currentQuestionIndex || 0) < orderedQuizQuestions.length - 1 ? (
              <Button
                className="bg-[#ffffff] hover:bg-[#e8e8e8] text-[#000000]"
                disabled={!currentQuizQuestionAnswered}
                onClick={onNextQuestion}
              >
                Seguinte
                <ChevronRight className="h-4 w-4 ml-1" />
              </Button>
            ) : (
              <Button
                className="bg-[#ffffff] hover:bg-[#e8e8e8] text-[#000000]"
                disabled={quizHasUnanswered}
                onClick={onSubmit}
              >
                Submeter Questionário
              </Button>
            )}
          </div>
        </>
      )}
    </section>
  );
}
