<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_spec_section_labels(): array
{
    return [
        'Network' => '네트워크',
        'Launch' => '출시 정보',
        'Body' => '외형',
        'Display' => '디스플레이',
        'Platform' => '플랫폼',
        'Memory' => '메모리',
        'Main Camera' => '후면 카메라',
        'Selfie camera' => '전면 카메라',
        'Selfie Camera' => '전면 카메라',
        'Camera' => '카메라',
        'Sound' => '사운드',
        'Comms' => '연결',
        'Features' => '기능',
        'Battery' => '배터리',
        'Misc' => '기타',
        'Tests' => '테스트',
    ];
}

function pc_spec_field_labels(): array
{
    return [
        'Technology' => '통신 기술',
        '2G bands' => '2G 주파수',
        '3G bands' => '3G 주파수',
        '4G bands' => '4G 주파수',
        '5G bands' => '5G 주파수',
        'Speed' => '통신 속도',
        'GPRS' => 'GPRS',
        'EDGE' => 'EDGE',
        'Announced' => '발표일',
        'Status' => '출시 상태',
        'Dimensions' => '크기',
        'Weight' => '무게',
        'Build' => '소재',
        'SIM' => 'SIM',
        'Type' => '종류',
        'Size' => '화면 크기',
        'Resolution' => '해상도',
        'Protection' => '화면 보호',
        'OS' => '운영체제',
        'Chipset' => '칩셋',
        'CPU' => 'CPU',
        'GPU' => 'GPU',
        'Card slot' => '메모리 카드',
        'Internal' => '내장 메모리',
        'Single' => '싱글 카메라',
        'Dual' => '듀얼 카메라',
        'Triple' => '트리플 카메라',
        'Quad' => '쿼드 카메라',
        'Penta' => '펜타 카메라',
        'Features' => '부가 기능',
        'Video' => '동영상',
        'Loudspeaker' => '스피커',
        '3.5mm jack' => '3.5mm 이어폰 단자',
        'WLAN' => 'Wi-Fi',
        'Bluetooth' => '블루투스',
        'Positioning' => '위치 측위',
        'GPS' => 'GPS',
        'NFC' => 'NFC',
        'Radio' => '라디오',
        'USB' => 'USB',
        'Infrared port' => '적외선 포트',
        'Sensors' => '센서',
        'Charging' => '충전',
        'Stand-by' => '대기 시간',
        'Talk time' => '통화 시간',
        'Music play' => '음악 재생',
        'Colors' => '색상',
        'Models' => '모델 번호',
        'SAR' => '전자파 흡수율',
        'SAR EU' => '전자파 흡수율(EU)',
        'Price' => '출시 가격',
        'Performance' => '성능',
        'Display' => '디스플레이',
        'Camera' => '카메라',
        'Loudspeaker' => '스피커',
        'Battery life' => '배터리 사용 시간',
        'Endurance rating' => '배터리 지속 점수',
        'Active use score' => '실사용 점수',
        'Contrast ratio' => '명암비',
        'Note' => '메모',
        '메모' => '메모',
    ];
}

function pc_translate_spec_section(?string $section): string
{
    if (!$section) {
        return '기타';
    }
    $labels = apply_filters('pc_spec_section_labels', pc_spec_section_labels());
    return $labels[$section] ?? $section;
}

function pc_translate_spec_field(?string $field): string
{
    if (!$field) {
        return '메모';
    }
    $labels = apply_filters('pc_spec_field_labels', pc_spec_field_labels());
    return $labels[$field] ?? $field;
}

function pc_spec_glossary(?string $section, ?string $field): ?string
{
    $terms = [
        'Network|Technology' => '지원하는 이동통신 세대를 뜻합니다. 5G 표기가 있으면 5G 네트워크를 사용할 수 있습니다.',
        'Body|Dimensions' => '기기의 세로·가로·두께를 밀리미터(mm) 단위로 표시합니다.',
        'Body|Weight' => '기기 본체의 무게입니다. 일반적으로 수치가 낮을수록 휴대가 편합니다.',
        'Body|SIM' => '사용할 수 있는 유심 규격과 듀얼 SIM, eSIM 지원 여부를 나타냅니다.',
        'Display|Type' => '화면 패널 종류와 주사율, HDR 같은 표시 기술을 설명합니다.',
        'Display|Resolution' => '화면을 구성하는 픽셀 수입니다. 같은 크기라면 수치가 높을수록 더 세밀하게 보입니다.',
        'Display|Protection' => '화면 유리에 적용된 긁힘과 충격 보호 기술입니다.',
        'Platform|Chipset' => 'CPU와 GPU 등을 묶은 핵심 반도체로 기기의 전반적인 성능과 효율에 영향을 줍니다.',
        'Platform|CPU' => '앱 실행과 연산을 담당하는 중앙처리장치의 코어 구성과 최대 속도입니다.',
        'Platform|GPU' => '게임과 그래픽 처리를 담당하는 프로세서입니다.',
        'Memory|Card slot' => 'microSD 같은 외장 메모리 카드로 저장공간을 확장할 수 있는지 나타냅니다.',
        'Memory|Internal' => '내장 저장공간과 RAM 조합입니다. RAM은 여러 앱을 동시에 실행하는 능력에 영향을 줍니다.',
        'Main Camera|Features' => 'OIS, HDR, 플래시 등 후면 카메라의 촬영 보조 기능입니다.',
        'Main Camera|Video' => '지원하는 최대 동영상 해상도와 초당 프레임 수를 나타냅니다.',
        'Sound|3.5mm jack' => '일반 유선 이어폰을 어댑터 없이 연결할 수 있는 단자입니다.',
        'Comms|NFC' => '근거리 무선통신 기능으로 교통카드나 비접촉 결제 등에 사용될 수 있습니다.',
        'Comms|Positioning' => 'GPS 등 위성 기반 위치 측위 시스템의 지원 범위입니다.',
        'Comms|USB' => '충전과 데이터 전송에 사용하는 단자 규격 및 지원 기능입니다.',
        'Battery|Type' => '배터리 방식과 용량입니다. mAh 수치가 크다고 실제 사용 시간이 항상 같은 비율로 늘지는 않습니다.',
        'Battery|Charging' => '유선·무선 충전 방식과 최대 충전 전력을 나타냅니다.',
        'Misc|SAR' => '인체가 흡수하는 전자파 에너지의 측정값입니다. 지역별 측정 기준이 다를 수 있습니다.',
        'Our Tests|Performance' => '동일한 벤치마크 도구로 측정된 경우에만 기기 간 수치를 직접 비교하는 것이 좋습니다.',
    ];
    return $terms[trim((string) $section) . '|' . trim((string) $field)] ?? null;
}

function pc_tech_section_labels(): array
{
    return [
        'Case' => '외형', 'Display' => '디스플레이', 'Battery' => '배터리',
        'CPU' => '프로세서', 'Graphics Card' => '그래픽카드', 'RAM' => '메모리',
        'Storage' => '저장장치', 'Sound' => '오디오', 'Connectivity' => '연결성',
        'Input' => '입력장치', 'General' => '기본 정보', 'Package' => '패키지',
        'Memory' => '메모리', 'Memory Support' => '메모리 지원', 'Physical' => '물리 사양',
        'Misc' => '기타', 'API' => '그래픽 API', 'Graphics Processing Unit' => '그래픽 프로세서',
        'Raw Performance' => '이론 성능', 'Performance Per Watt' => '전력당 성능',
        'Games' => '게임 성능', 'iGPU' => '내장 그래픽',
        'GeekBench v6' => 'Geekbench 6', 'GeekBench 6 GPU Compute' => 'Geekbench 6 GPU 연산',
        'GeekBench 6 ML' => 'Geekbench 6 머신러닝', 'GeekBench 6 OpenCL' => 'Geekbench 6 OpenCL',
        '3D Mark' => '3DMark', 'PassMark' => 'PassMark', 'Passmark Graphics' => 'PassMark 그래픽',
        'Cinebench' => 'Cinebench',
    ];
}

function pc_tech_key_labels(): array
{
    return [
        'Performance' => '성능', 'Gaming' => '게임 성능', 'Display' => '디스플레이',
        'Battery Life' => '배터리 사용시간', 'Connectivity' => '연결성', 'Portability' => '휴대성',
        'NanoReview Score' => '종합 평가', 'NanoReview Final Score' => '종합 평가',
        'Single-Core Performance' => '싱글 코어 성능', 'Multi-Core Performance' => '멀티 코어 성능',
        'Power Efficiency' => '전력 효율', 'Energy Efficiency' => '에너지 효율',
        'Integrated Graphics' => '내장 그래픽', 'Workstation' => '워크스테이션 성능',
        'AI/ML' => 'AI·머신러닝', 'Memory Bandwidth' => '메모리 대역폭',
        'Max. brightness' => '최대 밝기', 'Graphics Card' => '그래픽카드',
        'Battery' => '배터리', 'Storage' => '저장장치',

        'Weight' => '무게', 'Dimensions' => '크기', 'Area' => '면적',
        'Screen-to-body ratio' => '화면 대 본체 비율', 'Side bezels' => '측면 베젤',
        'Colors' => '색상', 'Material' => '소재', 'Transformer' => '2-in-1 변환',
        'Opening angle' => '최대 개방 각도', 'Cooling system' => '냉각 방식',
        'Vapor chamber' => '베이퍼 챔버', 'Number of fans' => '팬 개수',
        'Max. fan speed (RPM)' => '최대 팬 속도', 'Noise level (max. load)' => '최대 부하 소음',
        'Liquid metal' => '리퀴드 메탈',

        'Size' => '크기', 'Type' => '종류', 'Refresh rate' => '주사율',
        'Adaptive refresh rate' => '가변 주사율', 'PPI' => '픽셀 밀도',
        'Aspect ratio' => '화면 비율', 'Resolution' => '해상도', 'HDR support' => 'HDR 지원',
        'Sync technology' => '동기화 기술', 'Touchscreen' => '터치스크린',
        'Coating' => '화면 코팅', 'Ambient light sensor' => '주변광 센서',
        'Contrast' => '명암비', 'sRGB color space' => 'sRGB 색재현율',
        'Adobe RGB profile' => 'Adobe RGB 색재현율', 'DCI-P3 color gamut' => 'DCI-P3 색재현율',
        'Response time' => '응답속도', 'Max. brightness' => '최대 밝기',

        'Full charging time' => '완전 충전 시간', 'Battery type' => '배터리 종류',
        'Replaceable' => '교체 가능 여부', 'Fast charging' => '고속 충전',
        'Charging via USB (Power Delivery)' => 'USB-PD 충전', 'Charging port position' => '충전 단자 위치',
        'Charge power' => '충전 전력', 'Cable length' => '케이블 길이',
        'Weight of AC adapter' => '전원 어댑터 무게', 'Power' => '전력', 'Voltage' => '전압',

        'Base frequency' => '기본 클럭', 'Turbo frequency' => '최대 터보 클럭',
        'Cores' => '코어 수', 'Threads' => '스레드 수', 'Integrated GPU' => '내장 그래픽',
        'Fabrication process' => '제조 공정', 'Fabrication Process' => '제조 공정',
        'TGP' => '그래픽 전력', 'GPU base clock' => 'GPU 기본 클럭',
        'GPU boost clock' => 'GPU 부스트 클럭', 'GPU performance' => 'GPU 연산 성능',
        'Memory size' => '메모리 용량', 'Memory Size' => '메모리 용량',
        'Memory type' => '메모리 종류', 'Memory Type' => '메모리 종류',
        'Memory bus' => '메모리 버스', 'Memory speed' => '메모리 속도',
        'Shading units (cores)' => '셰이딩 유닛', 'Texture mapping units (TMUs)' => '텍스처 매핑 유닛',
        'Raster operations pipelines (ROPs)' => '래스터 출력 유닛',
        'Channels' => '채널 구성', 'Clock' => '클럭', 'Upgradable' => '업그레이드 가능',
        'Max. ram size' => '최대 메모리 용량', 'Total slots' => '전체 슬롯 수',
        'Bus' => '버스', 'Storage type' => '저장장치 종류', 'NVMe' => 'NVMe 지원',

        'Speakers' => '스피커', 'Dolby Atmos' => 'Dolby Atmos', 'Loudness' => '최대 음량',
        'Microphones' => '마이크', 'Audio chip' => '오디오 칩',
        'Wi-Fi standard' => 'Wi-Fi 규격', 'Bluetooth' => '블루투스',
        'Fingerprint' => '지문 인식', 'Optical drive' => '광학 드라이브',
        'Webcam' => '웹캠', 'Webcam resolution' => '웹캠 해상도',
        'USB-A' => 'USB-A', 'USB Type-C' => 'USB-C', 'Thunderbolt' => '썬더볼트',
        'Audio jack (3.5 mm)' => '3.5mm 오디오 단자', 'Ethernet (RJ45)' => '유선 LAN',
        'SD card reader' => 'SD 카드 리더', 'Proprietary charging port' => '전용 충전 단자',
        'Infrared sensor' => '적외선 센서', 'Keyboard type' => '키보드 방식',
        'Numpad' => '숫자 키패드', 'Backlight' => '키보드 백라이트',
        'Key travel' => '키 이동 거리', 'Surface' => '터치패드 표면',
        'Windows Precision' => 'Windows 정밀 터치패드',

        'Vendor' => '제조사', 'Released' => '출시일', 'Purpose' => '용도',
        'Segment' => '제품군', 'Architecture' => '아키텍처', 'Codename' => '코드명',
        'Model number' => '모델 번호', 'Instruction Set' => '명령어 집합',
        'Extended instructions' => '확장 명령어', 'Manufacturing' => '제조사',
        'Transistors' => '트랜지스터 수', 'Transistor Count' => '트랜지스터 수',
        'Transistor Density' => '트랜지스터 밀도', 'Die Size' => '다이 면적',
        'Launch price (MSRP)' => '출시 가격', 'Official Site' => '공식 사이트',
        'Successor' => '후속 제품', 'Build' => '설계',

        'P-Cores' => '성능 코어 수', 'P-Threads' => '성능 코어 스레드 수',
        'E-Cores' => '효율 코어 수', 'E-Threads' => '효율 코어 스레드 수',
        'Total Cores' => '전체 코어 수', 'Total Threads' => '전체 스레드 수',
        'Base Frequency (P)' => '성능 코어 기본 클럭', 'Turbo Boost Frequency (P)' => '성능 코어 최대 클럭',
        'Base Frequency (E)' => '효율 코어 기본 클럭', 'Turbo Boost Frequency (E)' => '효율 코어 최대 클럭',
        'Multiplier' => '배수', 'Unlocked Multiplier' => '배수 잠금 해제',
        'TDP (PL1)' => '기본 소비전력', 'Max. Boost TDP (PL2)' => '최대 부스트 소비전력',
        'Max. Temperature' => '최대 허용 온도', 'Peak Temperature' => '최고 온도',
        'Socket' => '소켓', 'L1 Cache' => 'L1 캐시', 'L2 Cache' => 'L2 캐시', 'L3 Cache' => 'L3 캐시',

        'Memory Types' => '지원 메모리', 'Max. Memory Size' => '최대 메모리 용량',
        'Memory Channels' => '메모리 채널', 'Max. Memory Bandwidth' => '최대 메모리 대역폭',
        'ECC Support' => 'ECC 지원', 'ECC' => 'ECC 지원',
        'PCI Express Version' => 'PCI Express 버전', 'PCI Express Lanes' => 'PCI Express 레인',

        'Base Clock' => '기본 클럭', 'Boost Clock' => '부스트 클럭',
        'GPU Base Clock' => 'GPU 기본 클럭', 'GPU Boost Clock' => 'GPU 부스트 클럭',
        'GPU Codename' => 'GPU 코드명', 'Compute Units (Pipelines)' => '컴퓨트 유닛',
        'Execution Units' => '실행 유닛', 'Shading Units' => '셰이딩 유닛',
        'TMUs' => '텍스처 매핑 유닛', 'ROPs' => '래스터 출력 유닛',
        'Texture Mapping Units (TMUs)' => '텍스처 매핑 유닛',
        'Render Output Units (ROPs)' => '래스터 출력 유닛',
        'Ray-tracing Cores' => '레이 트레이싱 코어', 'Tensor Cores' => '텐서 코어',
        'Effective Memory Speed' => '유효 메모리 속도', 'Memory Clock' => '메모리 클럭',
        'Bus Bandwidth' => '버스 대역폭', 'Bus Frequency' => '버스 속도',
        'Pixel Fill Rate' => '픽셀 필레이트', 'Texture Fill Rate' => '텍스처 필레이트',
        'Interface' => '인터페이스', 'Technologies' => '지원 기술',
        'AMD Equivalent' => 'AMD 동급 제품', 'Intel Equivalent' => 'Intel 동급 제품',
        'Nvidia Equivalent' => 'NVIDIA 동급 제품', 'Apple Equivalent' => 'Apple 동급 제품',
        'Qualcomm Equivalent' => 'Qualcomm 동급 제품', 'Recommended CPU' => '권장 CPU',
        'Used in CPUs' => '탑재 CPU',

        'File compression' => '파일 압축', 'Data compression' => '데이터 압축',
        'Data encryption' => '데이터 암호화', 'Clang compilation' => 'Clang 컴파일',
        'HTML 5 Browser' => 'HTML5 브라우저', 'PDF Renderer' => 'PDF 렌더링',
        'Text processing' => '텍스트 처리', 'Photo processing' => '사진 처리',
        'Background blur' => '배경 흐림 처리', 'Background Blur' => '배경 흐림 처리',
        'Ray tracing' => '레이 트레이싱', 'Ray Tracing' => '레이 트레이싱',
        'Find prime numbers' => '소수 계산', 'Random string sorting' => '문자열 정렬',
        'Floating point math' => '부동소수점 연산', 'Integer math' => '정수 연산',
        'Particle Physics' => '입자 물리 연산', 'Margin of Error' => '오차 범위',
        '1080p High' => '1080p 높음', '1080p Ultra' => '1080p 울트라',
        '1440p Ultra' => '1440p 울트라', '4K Ultra' => '4K 울트라',
    ];
}

function pc_translate_tech_section(?string $section): string
{
    $section = trim((string) $section);
    return pc_tech_section_labels()[$section] ?? $section;
}

function pc_translate_tech_key(?string $key): string
{
    $key = trim((string) $key);
    return pc_tech_key_labels()[$key] ?? $key;
}
