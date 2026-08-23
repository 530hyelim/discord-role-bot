# Goofy Bot

Discord.js 기반 커뮤니티 운영 봇

## 기술 스택

- Node.js 20
- discord.js 14
- Supabase (데이터 저장)
- node-cron (정기 작업)
- Docker
- OCI Ubuntu
- GitHub Actions

## 주요 기능

- `/question`, `/register` - 문제 출제/등록 퀴즈 게임, 정답 시 포인트 지급
- `/ranking` - 서버 내 포인트 랭킹 조회
- `/setup` - 채널/리액션 역할 등 서버 설정 (서버장 전용)
- `/announce` - 전체 공지 (최고 관리자 전용)
- `/anonymous` - 익명 메시지 전송
- `/report` - 버그 신고 / 카테고리·채점기준 추가 요청
- 신규 서버 참여 시 채널 자동 생성, 신규 멤버 환영 DM
- 리액션 역할, 음성채널 활동 시간 트래킹

봇은 Discord Gateway에 아웃바운드로 연결되는 구조라 별도의 웹 서비스는 없고, 외부 모니터링(UptimeRobot 등)용으로 가벼운 헬스체크 엔드포인트(`GET /`)만 열려 있습니다.

---

## 로컬 개발환경

### 필요한 것

- Docker Desktop ([다운로드](https://www.docker.com/products/docker-desktop/))
- Discord 봇 토큰, Supabase 프로젝트

### 실행

```bash
git clone https://github.com/530hyelim/goofy-bot.git
cd goofy-bot
# .env 파일 추가 (TOKEN, SUPABASE_URL, SUPABASE_KEY 등)
docker compose -f docker-compose.dev.yml up --build
```

### 개발 흐름

- 코드 수정 → 파일 변경 감지 후 컨테이너 안에서 자동 반영 (볼륨 마운트)
- 슬래시 커맨드는 `commands/` 디렉터리에 파일 추가 시 자동으로 로드됨

> 참고: `docker-compose.yml` / `Dockerfile`은 운영 배포용입니다. 로컬 개발은 항상 `docker-compose.dev.yml`을 사용하세요.

### 환경변수

`.env` 파일에 아래 값이 필요합니다.

| 변수 | 설명 |
|------|------|
| `TOKEN` | Discord 봇 토큰 |
| `SUPABASE_URL` | Supabase 프로젝트 URL |
| `SUPABASE_KEY` | Supabase API 키 |
| `PORT` | 헬스체크 서버 포트 (기본 3000) |

---

## 브랜치 전략

| 브랜치 | 용도 |
|--------|------|
| `main` | 운영 배포 (merge 시 자동 배포) |
| `feature/기능명` | 기능별 작업 브랜치 |

### 작업 흐름

1. 기능별 브랜치 생성
2. 작업 후 commit & push
3. GitHub에서 main으로 PR 생성
4. PR merge → 자동 배포

---

## 운영 배포

### 자동 배포

`main` 브랜치에 PR merge 시 GitHub Actions가 OCI 서버에 SSH 접속하여 자동 배포:

```
main에 push → GitHub Actions → OCI 서버 SSH →
git pull → docker compose build → docker compose up -d
```

### 수동 배포

```bash
ssh ubuntu@<서버IP>
cd /home/ubuntu/goofy-bot
git pull origin main
docker compose build
docker compose up -d
```

---

## 프로젝트 구조

```
goofy-bot/
├── index.js                          # 엔트리포인트 (Discord 클라이언트, 이벤트, 헬스체크 서버)
├── commands/                         # 슬래시 커맨드
├── services/                         # 리액션 역할, 음성 트래킹, 크론 작업
├── utils/                             # 공용 함수 (DB 접근, 에러 로깅 등)
├── Dockerfile                        # 운영용 이미지 빌드
├── docker-compose.yml                # 운영 배포 환경
├── docker-compose.dev.yml            # 로컬 개발 환경
└── .github/workflows/deploy.yml      # 자동 배포
```
