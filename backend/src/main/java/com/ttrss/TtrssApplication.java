package com.ttrss;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.scheduling.annotation.EnableScheduling;

/**
 * TTRSS Backend Application
 *
 * @author ttrss
 * @since 2026-03-24
 */
@SpringBootApplication
@EnableScheduling
public class TtrssApplication {

    public static void main(String[] args) {
        SpringApplication.run(TtrssApplication.class, args);
    }
}
